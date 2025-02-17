<?php

namespace App\Model\Product;

use App\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends BaseModel
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'products';

    protected $fillable = ['name', 'description', 'type', 'group', 'file', 'image', 'require_domain', 'category',
        'can_modify_agent',  'can_modify_quantity', 'show_agent', 'tax_apply', 'show_product_quantity', 'hidden',  'auto_terminate',
        'setup_order_placed', 'setup_first_payment', 'setup_accept_manually',
        'no_auto_setup', 'shoping_cart_link', 'process_url', 'github_owner',
        'github_repository',
        'deny_after_subscription', 'version', 'parent', 'subscription', 'product_sku', 'perpetual_license', ];

    protected static $logName = 'Product';

    protected static $logAttributes = ['name', 'description', 'type', 'file', 'category',
        'github_owner', 'github_repository', 'version',  'subscription', 'hidden', 'product_sku', ];

    protected static $logOnlyDirty = true;

    public function getDescriptionForEvent(string $eventName): string
    {
        if ($eventName == 'created') {
            return 'Product '.$this->name.' was created';
        }

        if ($eventName == 'updated') {
            return 'Product  <strong> '.$this->name.'</strong> was updated';
        }

        if ($eventName == 'deleted') {
            return 'Product <strong> '.$this->name.' </strong> was deleted';
        }

        return '';
    }

    // protected static $recordEvents = ['deleted'];

    public function order()
    {
        return $this->hasMany(\App\Model\Order\Order::class, 'product');
    }

    public function subscription()
    {
        return $this->hasMany(\App\Model\Product\Subscription::class);
    }

    public function licenseType()
    {
        return $this->belongsTo(\App\Model\License\LicenseType::class, 'type');
    }

    public function price()
    {
        return $this->hasMany(\App\Model\Product\Price::class);
    }

    public function PromoRelation()
    {
        return $this->hasMany(\App\Model\Payment\PromoProductRelation::class, 'product_id');
    }

    public function tax()
    {
        return $this->hasMany(\App\Model\Payment\TaxProductRelation::class, 'product_id');
    }

    public function productUpload()
    {
        return $this->hasMany(\App\Model\Product\ProductUpload::class, 'product_id');
    }

    public function delete()
    {
        $this->tax()->delete();
        $this->price()->delete();
        $this->PromoRelation()->delete();

        return parent::delete();
    }

    public function getImageAttribute($value)
    {
        if (! $value) {
            $image = asset('storage/common/images/No-image-found.jpg');
        } else {
            $image = asset("storage/common/images/$value");
        }

        return $image;
    }

    public function setParentAttribute($value)
    {
        $value = implode(',', $value);
        $this->attributes['parent'] = $value;
    }

    public function getParentAttribute($value)
    {
        $value = explode(',', $value);

        return $value;
    }

    public function planRelation()
    {
        $related = \App\Model\Payment\Plan::class;

        return $this->hasMany($related, 'product');
    }

    public function group()
    {
        return $this->belongsTo(\App\Model\Product\ProductGroup::class);
    }

    public function plan()
    {
        $plan = $this->planRelation()->first();

        return $plan;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
}
