@extends('themes.default1.layouts.master')
@section('title')
Cron Setting
@stop
@section('content-header')
    <div class="col-sm-6">
        <h1>{!! Lang::get('message.cron-setting') !!}</h1>
    </div>
    <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{url('/')}}"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="breadcrumb-item"><a href="{{url('settings')}}"><i class="fa fa-dashboard"></i> Settings</a></li>
            <li class="breadcrumb-item active">{!! Lang::get('message.cron-setting') !!}</li>
        </ol>
    </div><!-- /.col -->
@stop


@section('content')



    <div class="card card-secondary card-outline">
      <div class="row">
    <div class="col-md-12">
        <!-- Custom Tabs -->
  
               
                    @include('themes.default1.common.cron.cron-new')
               
                <!-- /.tab-pane -->


        <!-- nav-tabs-custom -->
    </div>
    <!-- /.col -->
</div>

</div>




<div class="row">
    <div class="col-md-12">
        <!-- Custom Tabs -->
  
     <div class="card card-secondary card-outline">
        
        <!-- /.box-header -->
       
              {!! Form::open(['url' => 'cron-days', 'method' => 'PATCH','id'=>'Form']) !!}
              <?php 
                   $mailStatus = \App\Model\Common\StatusSetting::pluck('expiry_mail')->first();
                   $activityStatus =\App\Model\Common\StatusSetting::pluck('activity_log_delete')->first();
                   $Autorenewal_status = \App\Model\Common\StatusSetting::pluck('subs_expirymail')->first();
                   $cloudStatus = \App\Model\Common\StatusSetting::pluck('cloud_mail_status')->first();
                  ?>
         <div class="card-header">
             <h3 class="card-title">{{Lang::get('message.set_cron_period')}}  </h3>


         </div>

      <div class="card-body">
          <div class="row">
           
            <!-- /.col -->
            <div class="col-md-6">
             
              <!-- /.form-group -->
              <div class="form-group select2">
                <label>{{Lang::get('message.expiry_mail_sent')}}</label> <i class="fas fa-question-circle" data-toggle="tooltip" data-placement="top" title="This cron is to trigger email which are sent out to users before product expiry reminding them to renew the product. This email is send out only to those who have not enabled auto renewal"></i>

                <?php 
                 if (count($selectedDays) > 0) {
                foreach ($selectedDays as $selectedDay) {
                    $saved[$selectedDay->days] = 'true';
                }
               }  else {
                    $saved=[];
                }
                 if (count($saved) > 0) {
                   foreach ($saved as $key => $value) {
                     $savedkey[]=$key;
                   }
                   $saved1=$savedkey?$savedkey:[''];
                       }
                       else{
                        $saved1=[];
                       }
                 ?>
                  
                   @if ($mailStatus == 0)
                    <select id ="days" name="expiryday" class="form-control selectpicker"   style="width: 100%; color:black;" disabled>
                      <option value="">{{Lang::get('message.enable_mail_cron')}}</option>
                    </select>
                      @else
                <select id ="days" name="expiryday" class="form-control selectpicker"  data-live-search="true" data-live-search-placeholder="Search" multiple="true" style="width: 100%; color:black;">

                    
                    @foreach ($expiryDays as $key=>$value)
                  <option value="{{$key}}" <?php echo (in_array($key, $saved1)) ?  "selected" : "" ;  ?>>{{$value}}</option>
                   @endforeach
                   
                </select>
                @endif
              </div>
              <!-- /.form-group -->
            </div>

             <div class="col-md-6">
              <div class="form-group">
                <label>{{Lang::get('message.log_del_days')}}</label>
                  @if ($activityStatus == 0)
                    <select id ="days" name="expiryday" class="form-control selectpicker"   style="width: 100%; color:black;" disabled>
                      <option value="">{{Lang::get('message.enable_activityLog_cron')}}</option>
                    </select>
                      @else
                <select name="logdelday" class="form-control selectpicker" data-live-search="true" data-live-search-placeholder="Search" style="width: 100%;">
                    @foreach ($delLogDays as $key=>$value)
                  <option value="{{$key}}" <?php echo (in_array($key, $beforeLogDay)) ?  "selected" : "" ;  ?>>{{$value}}</option>
                  @endforeach
                </select>
                @endif
              </div>
              <!-- /.form-group -->

              <!-- /.form-group -->
            </div>
            <!-- /.col -->

                <!-- /.col -->
            <div class="col-md-6">
             
              <!-- /.form-group -->
              <div class="form-group select2">
                <label>{{Lang::get('Subscription renewal reminder - Auto payment')}}</label>  <i class="fas fa-question-circle" data-toggle="tooltip" data-placement="top" title="This cron is to trigger email which are sent out to users before product expiry reminding them product will be renewed automatically. This email is send out only to those who have enabled auto renewal"></i>

                <?php 
                 if (count($selectedDays) > 0) {
                foreach ($selectedDays as $selectedDay) {
                    $saved[$selectedDay->days] = 'true';
                }
               }  else {
                    $saved=[];
                }
                 if (count($saved) > 0) {
                   foreach ($saved as $key => $value) {
                     $savedkey[]=$key;
                   }
                   $saved1=$savedkey?$savedkey:[''];
                       }
                       else{
                        $saved1=[];
                       }
                 ?>
                  
                   @if ($mailStatus == 0)
                    <select id ="days" name="subexpiryday" class="form-control selectpicker"   style="width: 100%; color:black;" disabled>
                      <option value="">{{Lang::get('message.enable_mail_cron')}}</option>
                    </select>
                      @else
                <select id ="days" name="subexpiryday" class="form-control selectpicker"  data-live-search="true" data-live-search-placeholder="Search" multiple="true" style="width: 100%; color:black;">

                    
                    @foreach ($Subs_expiry as $key=>$value)
                  <option value="{{$key}}" <?php echo (in_array($key, $Auto_expiryday)) ?  "selected" : "" ;  ?>>{{$value}}</option>
                   @endforeach
                   
          
                </select>
                @endif
              </div>
              <!-- /.form-group -->
            </div>

                <div class="col-md-6">
              <div class="form-group">
                <label>{{Lang::get('Cloud subscription deletion')}}</label>  <i class="fas fa-question-circle" data-toggle="tooltip" data-placement="top" title="This cron is to trigger email which are sent out to users after product expiry & on cloud instance deletion. This email is send out to all users using auto renewal or are using manual payment method. For cloud instance only"></i>
                  @if ($cloudStatus == 0)
                    <select id ="days" name="cloud_days[]" class="form-control selectpicker"   style="width: 100%; color:black;" disabled>
                      <option value="">{{Lang::get('Please Enable the Faveo cloud cron')}}</option>
                    </select>
                      @else
                <select name="cloud_days" class="form-control selectpicker" data-live-search="true" data-live-search-placeholder="Search" style="width: 100%;">
                    @foreach ($cloudDays as $key=>$value)
                  <option value="{{$key}}" <?php echo (in_array($key, $beforeCloudDay)) ?  "selected" : "" ;  ?>>{{$value}}</option>
                  @endforeach
                </select>
                @endif
              </div>
            </div>


                  <!-- /.col -->
            <div class="col-md-6">
             
              <!-- /.form-group -->
              <div class="form-group select2">
                <label>{{Lang::get('Subscription expired')}}</label>  <i class="fas fa-question-circle" data-toggle="tooltip" data-placement="top" title="This cron is to trigger email which are sent out to users after product expiry reminding them to renew the product. This email is send out to all users using auto renewal or are using manual payment method. For self hosted and cloud both"></i>

                <?php 
                 if (count($selectedDays) > 0) {
                foreach ($selectedDays as $selectedDay) {
                    $saved[$selectedDay->days] = 'true';
                }
               }  else {
                    $saved=[];
                }
                 if (count($saved) > 0) {
                   foreach ($saved as $key => $value) {
                     $savedkey[]=$key;
                   }
                   $saved1=$savedkey?$savedkey:[''];
                       }
                       else{
                        $saved1=[];
                       }
                 ?>
                  
                   @if ($mailStatus == 0)
                    <select id ="days" name="postsubexpiry_days" class="form-control selectpicker"   style="width: 100%; color:black;" disabled>
                      <option value="">{{Lang::get('message.enable_mail_cron')}}</option>
                    </select>
                      @else
                <select id ="days" name="postsubexpiry_days" class="form-control selectpicker"  data-live-search="true" data-live-search-placeholder="Search" multiple="true" style="width: 100%; color:black;">

                    
                    @foreach ($post_expiry as $key=>$value)
                  <option value="{{$key}}" <?php echo (in_array($key, $post_expiryday)) ?  "selected" : "" ;  ?>>{{$value}}</option>
                   @endforeach
                   
                </select>
                @endif
              </div>

              <!-- /.form-group -->
            </div>
          </div>
          <!-- /.row -->
          @if ( $mailStatus || $activityStatus || $cloudStatus ==1)
              <button type="submit" class="btn btn-primary pull-right" id="submit" data-loading-text="<i class='fa fa-circle-o-notch fa-spin'>&nbsp;</i> Saving..."><i class="fa fa-sync-alt">&nbsp;</i>{!!Lang::get('message.update')!!}</button>
          @else
              <button type="submit" class="btn btn-primary pull-right disabled" id="submit"><i class="fa fa-sync-alt">&nbsp;</i>{!!Lang::get('message.update')!!}</button>
          @endif
            {!! Form::close() !!}
        </div>
        <!-- /.box-body -->
       
      </div>
                <!-- /.tab-pane -->
            
         
        <!-- nav-tabs-custom -->
    </div>
    <!-- /.col -->
</div>



<script>
     $('ul.nav-sidebar a').filter(function() {
        return this.id == 'setting';
    }).addClass('active');

    // for treeview
    $('ul.nav-treeview a').filter(function() {
        return this.id == 'setting';
    }).parentsUntil(".nav-sidebar > .nav-treeview").addClass('menu-open').prev('a').addClass('active');
</script>
@stop


