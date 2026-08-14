<?php
/**
 * Dynamics NAV - 新增 Cash Receipt Journal 资料
 */

// ================== 配置区域 ==================
$wsdlFile    = __DIR__ . '/CashReceiveJournal.xml';
$location    = 'http://192.168.0.241:7047/DynamicsNAV70-OSGRP/WS/Oren%20Sport%20Sdn%20Bhd/Page/CashReceiveJournal';
$BatchName = '01-SLOAN';
$username    = 'autobilling';
$password    = '$^9AKsf#';
// ==============================================


class NTLMSoapClient extends SoapClient
{
    private $user;
    private $password;

    public function __construct($wsdl, $options = [])
    {
        $this->user     = $options['login'] ?? '';
        $this->password = $options['password'] ?? '';
        parent::__construct($wsdl, $options);
    }

    public function __doRequest($request, $location, $action, $version, $one_way = false): ?string
    {
        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $action . '"',
        ];

        $ch = curl_init($location);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $request,
            CURLOPT_HTTPAUTH       => CURLAUTH_NTLM,
            CURLOPT_USERPWD        => $this->user . ':' . $this->password,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("HTTP Error {$httpCode}\n" . substr($response, 0, 1200));
        }

        return $response;
    }
}


try {
    if (!file_exists($wsdlFile)) {
        die("找不到 WSDL 文件：" . $wsdlFile);
    }

    $options = [
        'login'          => $username,
        'password'       => $password,
        'location'       => $location,
        'uri'            => 'urn:microsoft-dynamics-schemas/page/cashreceivejournal',
        'trace'          => true,
        'exceptions'     => true,
        'cache_wsdl'     => WSDL_CACHE_NONE,
        'features'       => SOAP_SINGLE_ELEMENT_ARRAYS,
    ];

    $client = new NTLMSoapClient($wsdlFile, $options);

    // ================== 要新增的资料 ==================
    $newLine = [
        'Journal_Template_Name' => 'CASH RECEI',
        'Journal_Batch_Name'    => $BatchName,
        'Posting_Date'          => date('Y-m-d'),          // 今天日期
        'Document_Type'         => 'Payment',
        'Account_Type'          => 'Customer',
        'Account_No'            => 'C00003',               // 客户编号（请改成真实的）
        'Description'           => '测试新增 - PHP',
        'Amount'                => -100.00,                // 收款用负数
        'Bal_Account_Type'      => 'Bank_Account',
        'Bal_Account_No'        => 'MBBML-MYR223',         // 银行账户（请改成真实的）
        // 以下为可选字段
        // 'External_Document_No' => 'TSFR 123456',
        // 'Check_No'             => 'TSFR 123456',
        // 'Salespers_Purch_Code' => 'LONGHAN',
    ];

    // 调用 Create
    $params = [
        'CurrentJnlBatchName' => $BatchName,
        'CashReceiveJournal'  => $newLine
    ];

    $result = $client->__soapCall('Create', [$params]);

    echo "<h3 style='color:green;'>新增成功！</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";

    // 显示新增后的关键信息
    if (isset($result->CashReceiveJournal)) {
        $line = $result->CashReceiveJournal;
        echo "<hr>";
        echo "Document No：<b>" . ($line->Document_No ?? '') . "</b><br>";
        echo "Line No：" . ($line->Line_No ?? '') . "<br>";
        echo "Amount：" . ($line->Amount ?? '') . "<br>";
    }

} catch (Exception $e) {
    echo "<h3 style='color:red;'>新增失败</h3>";
    echo "<b>错误信息：</b><br>" . htmlspecialchars($e->getMessage());

    if (isset($client)) {
        echo "<hr><b>Last Request:</b><pre>" . htmlspecialchars($client->__getLastRequest() ?? '') . "</pre>";
        echo "<b>Last Response:</b><pre>" . htmlspecialchars($client->__getLastResponse() ?? '') . "</pre>";
    }
}