<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Http\Controllers\License\LicenseController;
use App\Model\Common\FaveoCloud;
use App\Model\Common\Setting;
use App\Model\Common\StatusSetting;
use App\Model\Order\Order;
use App\Model\Product\Subscription;
use App\ThirdPartyApp;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    private $cloud;

    public function __construct(Client $client, FaveoCloud $cloud)
    {
        $this->client = $client;
        $this->cloud = $cloud->first();

        $this->middleware('auth', ['except' => ['verifyThirdPartyToken']]);
    }

    public function viewTenant()
    {
        if ($this->cloud && $this->cloud->cloud_central_domain) {
            $cloud = $this->cloud;
            $keys = ThirdPartyApp::where('app_name', 'faveo_app_key')->select('app_key', 'app_secret')->first();

            if (! $keys->app_key) {//Valdidate if the app key to be sent is valid or not
                throw new Exception('Invalid App key provided. Please contact admin.');
            }
            $response = $this->client->request(
                'GET',
                $this->cloud->cloud_central_domain.'/tenants',
                [
                    'query' => [
                        'key' => $keys->app_key,
                    ],
                ]
            );

            $responseBody = (string) $response->getBody();
            $responseData = json_decode($responseBody, true);

            $de = collect($responseData['message'])->paginate(5);
        } else {
            $de = null;
            $cloudButton = null;
            $cloud = null;
        }
        $cloudButton = StatusSetting::value('cloud_button');

        return view('themes.default1.tenant.index', compact('de', 'cloudButton', 'cloud'));
    }

    public function enableCloud(Request $request)
    {
        try {
            $request->input('debug') == 'true' ? StatusSetting::where('id', '1')->update(['cloud_button' => '1']) : StatusSetting::where('id', '1')->update(['cloud_button' => '0']);

            return redirect()->back()->with('success', \Lang::get('message.updated-successfully'));
        } catch(\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function getTenants(Request $request)
    {
        try {
            $keys = ThirdPartyApp::where('app_name', 'faveo_app_key')->select('app_key', 'app_secret')->first();

            if (! $keys->app_key) {//Valdidate if the app key to be sent is valid or not
                throw new Exception('Invalid App key provided. Please contact admin.');
            }
            $response = $this->client->request(
                'GET',
                $this->cloud->cloud_central_domain.'/tenants',
                [
                    'query' => [
                        'key' => $keys->app_key,
                    ],
                ]
            );

            $responseBody = (string) $response->getBody();
            $responseData = json_decode($responseBody);

            $collection = collect($responseData->message)->reject(function ($item) {
                return $item === null;
            });

            return \DataTables::collection($collection)
                ->addColumn('Order', function ($model) {
                    $order_id = \DB::table('installation_details')->where('installation_path', $model->domain)->value('order_id');
                    $order_number = \DB::table('orders')->where('id', $order_id)->value('number');

                    if (empty($order_id) || empty($order_number)) {
                        return '--';
                    }

                    return "<p><a href='".url('/orders/'.$order_id)."'>$order_number</a></p>";
                })
                ->addColumn('Deletion day', function ($model) {
                    $order_id = \DB::table('installation_details')->where('installation_path', $model->domain)->value('order_id');
                    $subscription_date = Subscription::where('order_id', $order_id)->value('ends_at');
                    if (empty($subscription_date)) {
                        return '--';
                    }
                    $days = \DB::table('expiry_mail_days')->where('cloud_days', '!=', null)->value('cloud_days');
                    $originalDate = Carbon::parse($subscription_date)->addDays($days);
                    $formattedDate = Carbon::parse($originalDate)->format('d M Y');

                    return $formattedDate;
                })

                ->addColumn('tenants', function ($model) {
                    return $model->id ?? '';
                })
                ->addColumn('domain', function ($model) {
                    return '<a href="http://'.$model->domain.'" target="_blank">'.$model->domain.'</a>';
                })
                ->addColumn('db_name', function ($model) {
                    return $model->database_name ?? '';
                })
                ->addColumn('db_username', function ($model) {
                    return $model->database_user_name ?? '';
                })
                ->addColumn('action', function ($model) {
                    $order_id = \DB::table('installation_details')->where('installation_path', $model->domain)->value('order_id');
                    $order_number = \DB::table('orders')->where('id', $order_id)->value('number');

                    if (empty($order_id) || empty($order_number)) {
                        return "<p><button data-toggle='modal'
                data-id=".$model->id." data-name= '' onclick=deleteTenant('".$model->id."') id='delten".$model->id."'
                class='btn btn-sm btn-danger btn-xs delTenant'".tooltip('Delete')."<i class='fa fa-trash'
                style='color:white;'> </i></button>&nbsp;</p>";
                    }

                    return "<p><button data-toggle='modal'
                data-id='".$model->id."' data-name='' onclick=\"deleteTenant('".$model->id."','".$order_number."')\" id='delten".$model->id."'
                class='btn btn-sm btn-danger btn-xs delTenant' ".tooltip('Delete')."<i class='fa fa-trash'
                style='color:white;'> </i></button>&nbsp;</p>";
                })
                ->rawColumns(['Order', 'Deletion day', 'tenants', 'domain', 'db_name', 'db_username', 'action'])
                ->make(true);
        } catch (ConnectException|Exception $e) {
            return redirect()->back()->with('fails', $e->getMessage());
        }
    }

    private function postCurl($post_url, $post_info)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $post_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_info);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * Logic for creating new tenant is handled here.
     */
    public function createTenant(Request $request)
    {
        $order = Order::wherenumber($request->orderNo)->get();
        $product = strpos($order[0]->product()->value('name'), 'ServiceDesk') ? 'ServiceDesk' : 'Helpdesk';
        //This above code is only written
        // to differentiate HD and SD when we reach the
        // market place feature this needs to be removed

        $this->validate($request,
            [
                'orderNo' => 'required',
                'domain' => 'required||regex:/^[a-zA-Z0-9]+$/u',
            ],
            [
                'domain.regex' => 'Special characters are not allowed in domain name',
            ]);

        $settings = Setting::find(1);
        $user = \Auth::user()->email;
        $mail = new \App\Http\Controllers\Common\PhpMailController();

        try {
            $company = (string) $request->input('domain');

            // Convert spaces to underscores
            $company = str_replace(' ', '', $company);

            // Convert uppercase letters to lowercase
            $faveoCloud = strtolower($company).'.faveocloud.com';

            $dns_record = dns_get_record($faveoCloud, DNS_CNAME);
            if (! strpos($faveoCloud, 'faveocloud.com')) {
                if (empty($dns_record) || ! in_array('faveocloud.com', array_column($dns_record, 'target'))) {
                    return ['status' => 'false', 'message' => trans('message.cname')];
                }
            }
            $licCode = Order::where('number', $request->input('orderNo'))->first()->serial_key;
            $keys = ThirdPartyApp::where('app_name', 'faveo_app_key')->select('app_key', 'app_secret')->first();
            if (! $keys->app_key) {//Valdidate if the app key to be sent is valid or not
                throw new Exception('Invalid App key provided. Please contact admin.');
            }
            $token = str_random(32);
            \DB::table('third_party_tokens')->insert(['user_id' => \Auth::user()->id, 'token' => $token]);
            $client = new Client([]);
            $data = ['domain' => $faveoCloud, 'app_key'=>$keys->app_key, 'token'=>$token, 'lic_code'=>$licCode, 'username'=>$user, 'userId'=>\Auth::user()->id, 'timestamp'=>time(), 'product'=>$product, 'product_id'=>$order[0]->product()->value('id')];
            $encodedData = http_build_query($data);
            $hashedSignature = hash_hmac('sha256', $encodedData, $keys->app_secret);
            $response = $client->request(
                'POST',
                $this->cloud->cloud_central_domain.'/tenants', ['form_params'=>$data, 'headers'=>['signature'=>$hashedSignature]]
            );

            $response = explode('{', (string) $response->getBody());

            $response = '{'.$response[1];

            $result = json_decode($response);
            if ($result->status == 'fails') {
                $this->prepareMessages($faveoCloud, $user);

                $this->googleChat($result->message);

                if ($result->message == 'Domain already taken. Please select a different domain') {
                    return ['status' => 'false', 'message' => $result->message];
                }

                return ['status' => 'false', 'message' => trans('message.something_bad')];
            } elseif ($result->status == 'validationFailure') {
                $this->prepareMessages($faveoCloud, $user);

                $this->googleChat($result->message);

                return ['status' => 'validationFailure', 'message' => $result->message];
            } else {
                if (! strpos($faveoCloud, 'faveocloud.com')) {
                    CloudEmail::create([
                        'result_message' => $result->message,
                        'user' => $user,
                        'result_password' => $result->password,
                        'domain' => $faveoCloud,
                    ]);
                    $client = new Client();
                    $client->request('GET', env('CLOUD_JOB_URL'), [
                        'auth' => [env('CLOUD_USER'), env('CLOUD_AUTH')],
                        'query' => [
                            'token' => env('CLOUD_OAUTH_TOKEN'),
                            'domain' => $faveoCloud,
                        ],
                    ]);

                    return ['status' => $result->status, 'message' => trans('message.create_in_progress').'.'];
                } else {
                    $client->request('GET', env('CLOUD_JOB_URL_NORMAL'), [
                        'auth' => [env('CLOUD_USER'), env('CLOUD_AUTH')],
                        'query' => [
                            'token' => env('CLOUD_OAUTH_TOKEN'),
                            'domain' => $faveoCloud,
                        ],
                    ]);

                    //template
                    $template = new \App\Model\Common\Template();
                    $temp_type_id = \DB::table('template_types')->where('name', 'cloud_created')->value('id');
                    $template = $template->where('type', $temp_type_id)->first();
                    $contact = getContactData();

                    $type = '';
                    if ($template) {
                        $type_id = $template->type;
                        $temp_type = new \App\Model\Common\TemplateType();
                        $type = $temp_type->where('id', $type_id)->first()->name;
                    }
                    $subject = 'Your '.$order[0]->product()->value('name').' is now ready for use. Get started!';
                    $result->message = str_replace('website', strtolower($product), $result->message);
                    $result->message = str_replace('You will receive password on your registered email', '', $result->message);
                    $userData = $result->message.'<br><br> Email:'.' '.$user.'<br>'.'Password:'.' '.$result->password;

                    $replace = [
                        'message' => $userData,
                        'product' => $order[0]->product()->value('name'),
                        'name' => \Auth::user()->first_name.' '.\Auth::user()->last_name,
                        'contact' => $contact['contact'],
                        'logo' => $contact['logo'],
                        'title' => $settings->title,
                        'company_email' => $settings->company_email,
                    ];

                    $this->prepareMessages($faveoCloud, $user, true);
                    $mail->SendEmail($settings->email, $user, $template->data, $subject, $replace, $type);

                    return ['status' => $result->status, 'message' => $result->message.trans('message.cloud_created_successfully')];
                }
            }
        } catch (Exception $e) {
            $message = $e->getMessage().' Domain: '.$faveoCloud.' Email: '.$user;
            $this->googleChat($message);

            return ['status' => 'false', 'message' => trans('message.something_bad')];
        }
    }

    public function verifyThirdPartyToken(Request $request)
    {
        try {
            $token = $request->input('token');
            $userId = $request->input('userId');
            $faveoToken = \DB::table('third_party_tokens')->where('user_id', $userId)->value('token');
            if ($faveoToken && $token == $faveoToken) {
                \DB::table('third_party_tokens')->where('user_id', $userId)->delete();
                //delete third party token here
                $response = ['status' => 'success', 'message' => 'Valid token'];
            } else {
                $response = ['status' => 'fails', 'message' => 'Invalid token'];
            }

            return $response;
        } catch (Exception $e) {
            $error = ['status' => 'fails', 'message' => $e->getMessage()];

            return $error;
        }
    }

    public function destroyTenant(Request $request)
    {
        try {
            $keys = ThirdPartyApp::where('app_name', 'faveo_app_key')->select('app_key', 'app_secret')->first();
            $token = str_random(32);
            $data = ['id' => $request->input('id'), 'app_key' => $keys->app_key, 'deleteTenant' => true, 'token' => $token, 'timestamp' => time()];
            $encodedData = http_build_query($data);
            $hashedSignature = hash_hmac('sha256', $encodedData, $keys->app_secret);
            $client = new Client([]);
            $response = $client->request(
                'DELETE',
                $this->cloud->cloud_central_domain.'/tenants', ['form_params' => $data, 'headers' => ['signature' => $hashedSignature]]
            );
            $responseBody = (string) $response->getBody();
            $response = json_decode($responseBody);
            if ($response->status == 'success') {
                $this->deleteCronForTenant($request->input('id'));
                \DB::table('free_trial_allowed')->where('domain', $request->input('id'))->delete();
                (empty($request->orderId)) ?: Order::where('id', $request->get('orderId'))->delete();
                (new LicenseController())->reissueDomain($request->input('id'));

                return successResponse($response->message);
            } else {
                return errorResponse($response->message);
            }
        } catch (Exception $e) {
            return errorResponse($e->getMessage());
        }
    }

    private function deleteCronForTenant($tenantId)
    {
        $client = new Client();
        if (strpos($tenantId, 'faveocloud.com')) {
            $client->request('GET', env('CLOUD__DELETE_JOB_URL_NORMAL'), [
                'auth' => [env('CLOUD_USER'), env('CLOUD_AUTH')],
                'query' => [
                    'token' => env('CLOUD_OAUTH_TOKEN'),
                    'domain' => $tenantId,
                ],
            ]);
        } else {
            $client->request('GET', env('CLOUD__DELETE_JOB_URL_CUSTOM'), [
                'auth' => [env('CLOUD_USER'), env('CLOUD_AUTH')],
                'query' => [
                    'token' => env('CLOUD_OAUTH_TOKEN'),
                    'domain' => $tenantId,
                ],
            ]);
        }
    }

    public function saveCloudDetails(Request $request)
    {
        $this->validate($request, [
            'cloud_central_domain' => 'required',
        ]);

        try {
            $cloud = new FaveoCloud;
            $cloud->updateOrCreate(['id' => 1], ['cloud_central_domain' => $request->input('cloud_central_domain'), 'cron_server_url' => $request->input('cron_server_url'),
                'cron_server_key' => $request->input('cron_server_key'), ]);
            // $cloud->first()->fill($request->all())->save();
            return redirect()->back()->with('success', \Lang::get('message.updated-successfully'));
        } catch (Exception $e) {
            return redirect()->back()->with('fails', $e->getMessage());
        }
    }

    public function DeleteCloudInstanceForClient($orderNumber, $isDelete)
    {
        if ($isDelete) {
            $keys = ThirdPartyApp::where('app_name', 'faveo_app_key')->select('app_key', 'app_secret')->first();
            $token = str_random(32);
            $order_id = Order::where('number', $orderNumber)->where('client', \Auth::user()->id)->value('id');
            $installation_path = \DB::table('installation_details')->where('order_id', $order_id)->where('installation_path', '!=', 'billing.faveocloud.com')->value('installation_path');
            $response = $this->client->request(
                'GET',
                $this->cloud->cloud_central_domain.'/tenants', [
                    'query' => [
                        'key' => $keys->app_key,
                    ],
                ]
            );
            $responseBody = (string) $response->getBody();
            $response = json_decode($responseBody);
            $domainArray = $response->message;
            for ($i = 0; $i < count($domainArray); $i++) {
                if ($domainArray[$i]->domain == $installation_path) {
                    $data = ['id' => $domainArray[$i]->id, 'app_key'=>$keys->app_key, 'deleteTenant'=> true, 'token'=>$token, 'timestamp'=>time()];
                    $encodedData = http_build_query($data);
                    $hashedSignature = hash_hmac('sha256', $encodedData, $keys->app_secret);
                    $client = new Client([]);
                    $response = $client->request(
                        'DELETE',
                        $this->cloud->cloud_central_domain.'/tenants', ['form_params'=>$data, 'headers'=>['signature'=>$hashedSignature]]
                    );
                    $responseBody = (string) $response->getBody();
                    $response = json_decode($responseBody);
                    if ($response->status == 'success') {
                        $this->deleteCronForTenant($domainArray[$i]->id);
                        $this->reissueCloudLicense($order_id);
                        Order::where('number', $orderNumber)->where('client', \Auth::user()->id)->delete();
                        \DB::table('free_trial_allowed')->where('domain', $installation_path)->delete();

                        return redirect()->back()->with('success', $response->message);
                    } else {
                        return redirect()->back()->with('fails', $response->message);
                    }
                }
            }

            return redirect()->back()->with('fails', "Something went wrong, we couldn't delete your cloud instance.(mostly your cloud instance was already deleted)");
        }
    }

    protected function reissueCloudLicense($order_id)
    {
        $order = Order::findorFail($order_id);
        if (\Auth::user()->role != 'admin' && $order->client != \Auth::user()->id) {
            return errorResponse('Cannot remove license installations. Invalid modification of data');
        }
        $order->domain = '';
        $licenseCode = $order->serial_key;
        $order->save();
        $licenseStatus = \DB::table('status_settings')->pluck('license_status')->first();
        if ($licenseStatus == 1) {
            $licenseExpiry = $order->subscription->ends_at;
            $updatesExpiry = $order->subscription->update_ends_at;
            $supportExpiry = $order->subscription->support_ends_at;
            $cont = new \App\Http\Controllers\License\LicenseController();
            $updateLicensedDomain = $cont->updateLicensedDomain($licenseCode, $order->domain, $order->product, $licenseExpiry, $updatesExpiry, $supportExpiry, $order->number);
            //Now make Installation status as inactive
            $updateInstallStatus = $cont->updateInstalledDomain($licenseCode, $order->product);
        }

        return ['message' => 'success', 'update'=>'License installations removed'];
    }

    private function prepareMessages($domain, $user, $success = false)
    {
        if ($success) {
            $this->googleChat('Hello, It has come to my notice that this domain has been created successfully Domain name:'.$domain.' and this is their email: '.$user."\u{2705}\u{2705}\u{2705}");
        } else {
            $this->googleChat('Hello, It has come to my notice that this domain has not been created successfully Domain name:'.$domain.' and this is their email: '.$user.'&#10060;'."\u{2716}\u{2716}\u{2716}");
        }
    }

    private function googleChat($text)
    {
        $url = env('GOOGLE_CHAT');
        $message = [
            'text' => $text,
        ];
        $message_headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];
        $client = new Client();
        $client->post($url, [
            'headers' => $message_headers,
            'body' => json_encode($message),
        ]);
    }
}
