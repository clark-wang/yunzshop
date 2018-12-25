@extends('layouts.base')

@section('content')

    <script type="text/javascript">
        function formcheck() {
            return true;

        }
    </script>
<div class="w1200 m0a">
<div class="rightlist">

    @include('layouts.tabs')
    <form action="" method="post" class="form-horizontal form" enctype="multipart/form-data" >
        <div class="panel panel-default">
            <div class='panel-body'>

                <div class="form-group">
                    <label class="col-xs-12 col-sm-3 col-md-2 control-label">是否收费接口</label>
                    <div class="col-sm-9 col-xs-12">
                        <label class='radio-inline'><input type='radio' name='express_info[KDN][express_api]' value='1002'
                                                           @if ($set['KDN']['express_api'] == 1002 || empty($set['KDN']['express_api'])) checked @endif  />否</label>
                        <label class='radio-inline'><input type='radio' name='express_info[KDN][express_api]' value='8001'
                                                           @if ($set['KDN']['express_api'] == 8001) checked @endif/> 是</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-xs-12 col-sm-3 col-md-2 control-label">用户ID</label>
                    <div class="col-sm-9 col-xs-12">
                        <input type="text" name="express_info[KDN][eBusinessID]" class="form-control" value="{{ $set['KDN']['eBusinessID'] }}" />
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-xs-12 col-sm-3 col-md-2 control-label">API key</label>
                    <div class="col-sm-9 col-xs-12">
                        <input type="text" name="express_info[KDN][appKey]" class="form-control" value="{{ $set['KDN']['appKey'] }}" />
                    </div>
                </div>
                       <div class="form-group"></div>
            <div class="form-group">
                    <label class="col-xs-12 col-sm-3 col-md-2 control-label"></label>
                    <div class="col-sm-9 col-xs-12">
                            <input type="submit" name="submit" value="提交" class="btn btn-success " onclick="return formcheck()" />
                     </div>
            </div>

            </div>
        </div>     
    </form>
</div>
</div>
@endsection
