﻿ <script language="javascript">
        function pay()
        {
            var order_id = $('.order_id').val();
            if (confirm('确认此订单已付款吗？')) {
                $.post("{!! yzWebUrl('order.operation.pay',['order_id'=>$order['id']]) !!}", function(json){
                    location.href = location.href;
                });
            }
        }
    </script>
@if ($order['status'] == 0)
<a class="btn btn-primary btn-sm disbut"
   href="javascript:;"
   onclick="pay()">确认付款</a>
<a class="label label-default">等待付款</a>
@endif

@if ($order['status'] == 1)
<div>
    <input class='addressdata' type='hidden' value='{{$order['has_one_address']['address']}}' />
    <input class='itemid' type='hidden' value="{{$order['id']}}"/>
    <a class="btn btn-primary btn-sm disbut" href="javascript:;" onclick="send(this)" data-toggle="modal"
       data-target="#modal-confirmsend">确认发货</a>
</div>
@endif

@if ($order['status'] == 2)
<a class="btn btn-danger btn-sm disbut" href="javascript:;"
   onclick="$('#modal-cancelsend').find(':input[name=order_id]').val('{{$order['id']}}')" data-toggle="modal"
   data-target="#modal-cancelsend">取消发货</a>
<a class="btn btn-primary btn-sm disbut"
   href="{!! yzWebUrl('order.complete-receive', array('order_id' => $order['id'])) !!}"
   onclick="return confirm('确认订单收货吗？');return false;">确认收货</a>
<a class="btn btn-default btn-sm disbut">等待收货</a>
@endif



