<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2017/6/5
 * Time: 下午7:53
 */

namespace app\frontend\modules\order\services;


use app\frontend\modules\order\services\message\BuyerMessage;
use app\frontend\modules\order\services\message\ShopMessage;

class MessageService extends \app\common\services\MessageService
{
    private $buyerMessage;
    private $shopMessage;
    protected $formId;
    protected $noticeType;
    function __construct($order,$formId = '',$type = 1)
    {
        $this->buyerMessage = new BuyerMessage($order,$formId,$type);
        $this->shopMessage = new ShopMessage($order,$formId,$type);
        $this->formId = $formId;
        $this->noticeType = $type;
    }

    public function canceled()
    {
        $this->buyerMessage->canceled();

    }

    public function created()
    {
        $this->shopMessage->goodsBuy(1);
        $this->buyerMessage->created();
        if (\Setting::get('shop.notice.notice_enable.created')) {
            $this->shopMessage->created();
        }
    }

    public function paid()
    {
        $this->shopMessage->goodsBuy(2);
        $this->buyerMessage->paid();

        if (\Setting::get('shop.notice.notice_enable.paid')) {
            $this->shopMessage->paid();
        }

    }

    public function sent()
    {
        $this->buyerMessage->sent();

    }

    public function received()
    {
        $this->shopMessage->goodsBuy(3);
        if ($this->noticeType == 2){
            $noticeUrl = 'shop.miniNotice.notice_enable.received';
        }else{
            $noticeUrl = 'shop.notice.notice_enable.received';
        }
        if (\Setting::get($noticeUrl)) {
            $this->shopMessage->received();
        }
        $this->buyerMessage->received();
    }
}