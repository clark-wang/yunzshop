<?php

/* ҳ��ת����ʱ����֪ͨҳ��ص�ʾ��  */

require_once ("./classes/GatewayNotify.class.php");

/* �̻���Կ */
$key = "857e6g8y51b5k365f7v954s50u24h14w";


$notify = new GatewayNotify();
$notify->setKey($key);

//��֤ǩ��
if($notify->verifySign()) {
	
	$busi_code = $notify->getParameter("busi_code");
	$merchant_no = $notify->getParameter("merchant_no");
	$terminal_no = $notify->getParameter("terminal_no");
	$order_no = $notify->getParameter("order_no");
	$pay_no = $notify->getParameter("pay_no");
	$amount = $notify->getParameter("amount");
	$pay_result = $notify->getParameter("pay_result");
	$pay_time = $notify->getParameter("pay_time");
	$sett_date = $notify->getParameter("sett_date");
	$sett_time = $notify->getParameter("sett_time");
	$base64_memo = $notify->getParameter("base64_memo");
	$sign_type = $notify->getParameter("sign_type");
	$sign = $notify->getParameter("sign");
	$memo = base64_decode($base64_memo);

	if( "1" == $pay_result ) {

		//����ҵ��ʼ
		echo "</br>��ȡ�첽֪ͨ��Ϣ�ɹ�!</br></br>";	
		echo " success "."</br></br>";
		echo "ҵ����룺".$busi_code."</br>";
		echo "�̻��ţ�".$merchant_no."</br>";
		echo "�ն˺ţ�".$terminal_no."</br>";
		echo "�̻�ϵͳ�����ţ�".$order_no."</br>";
		echo "����ϵͳ֧���ţ�".$pay_no."</br>";
		echo "������".$amount."</br>";
		echo "֧�������1��ʾ�ɹ�����".$pay_result."</br>";
		echo "֧��ʱ�䣺".$pay_time."</br>";
		echo "�������ڣ�".$sett_date."</br>";
		echo "����ʱ�䣺".$sett_time."</br>";
		echo "������ע��".$memo."</br>";
		echo "ǩ�����ͣ�".$sign_type."</br>";
		echo "ǩ����".$sign."</br>";

		//ע�ⶩ����Ҫ�ظ�����
		//ע���жϷ��ؽ���Ƿ��뱾ϵͳ������
		//����ҵ�����
	
	} else {
		//����֪ͨ�����ɹ�
		echo "֧��ʧ�ܣ�</br></br>";
		echo "�̻�ϵͳ�����ţ�".$order_no."</br>";
		echo "����ϵͳ֧���ţ�".$pay_no."</br>";
		echo "֧�������0��ʾδ֧����2��ʾ֧��ʧ�ܣ���".$pay_result."</br>";
	}
	
} else {
	echo "<br/>" . "��֤ǩ��ʧ��" ;
}

//��ȡ������Ϣ
//echo $notify->getDebugMsg() ;

?>