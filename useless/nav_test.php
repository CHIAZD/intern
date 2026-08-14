<?php
/**
 * Dynamics NAV - Cash Receipt Journal Reader
 * 完整可用版本
 */

// ================== 配置区域（请修改这里） ==================
$wsdlFile    = __DIR__ . '/CashReceiveJournal.xml';   // 本地 WSDL 文件路径
$location    = 'http://192.168.0.241:7047/DynamicsNAV70-OSGRP/WS/Oren%20Sport%20Sdn%20Bhd/Page/CashReceiveJournal';
$BatchName = '01-SLOAN';
$username    = 'autobilling';          // 例如：DOMAIN\username 或 username
$password    = '$^9AKsf#';
// ==========================================================


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
            throw new Exception("HTTP Error {$httpCode}\n" . substr($response, 0, 1000));
        }

        return $response;
    }
}


// ================== 主程序 ==================
try {
    if (!file_exists($wsdlFile)) {
        die("错误：找不到本地 WSDL 文件 → " . $wsdlFile);
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

    // 过滤条件
    $filters = [
        [
            'Field'    => 'Journal_Template_Name',
            'Criteria' => 'CASH RECEI'
        ],
        [
            'Field'    => 'Journal_Batch_Name',
            'Criteria' => $BatchName
        ]
    ];

    // 调用 ReadMultiple
    $params = [
        'CurrentJnlBatchName' => $BatchName,
        'filter'              => $filters,
        'bookmarkKey'         => null,
        'setSize'             => 0          // 0 = 读取全部
    ];

    $result = $client->__soapCall('ReadMultiple', [$params]);

    // 取出资料
    $lines = $result->ReadMultiple_Result->CashReceiveJournal ?? [];

    // 如果只有一笔资料，会是对象而不是数组，统一转成数组
    if (!empty($lines) && !is_array($lines)) {
        $lines = [$lines];
    }

    // ================== 显示结果 ==================
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <title>Cash Receipt Journal</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th { background: #4472C4; color: white; padding: 8px; text-align: left; }
            td { border: 1px solid #ccc; padding: 6px 8px; }
            tr:nth-child(even) { background: #f5f5f5; }
            .amount { text-align: right; }
            h2 { color: #333; }
        </style>
    </head>
    <body>";

    echo "<h2>Cash Receipt Journal - " . $BatchName . "</h2>";
    echo "<p>共读取到 <b>" . count($lines) . "</b> 笔资料</p>";

    if (empty($lines)) {
        echo "<p>目前没有资料。</p>";
    } else {
        echo "<table>
                <tr>
                    <th>Line No</th>
                    <th>Posting Date</th>
                    <th>Document No</th>
                    <th>Account No</th>
                    <th>Customer Name</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Bal Account</th>
                    <th>Salesperson</th>
                </tr>";

        foreach ($lines as $line) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($line->Line_No ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($line->Posting_Date ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($line->Document_No ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($line->Account_No ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($line->AccName ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($line->Description ?? '') . "</td>";
            echo "<td class='amount'>" . number_format($line->Amount ?? 0, 2) . "</td>";
            echo "<td>" . htmlspecialchars($line->Bal_Account_No ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($line->Salespers_Purch_Code ?? '') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    echo "</body></html>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>发生错误</h3>";
    echo "<b>错误信息：</b> " . htmlspecialchars($e->getMessage());

    if (isset($client)) {
        echo "<hr><b>Last Request:</b><pre>" . htmlspecialchars($client->__getLastRequest() ?? '') . "</pre>";
        echo "<b>Last Response:</b><pre>" . htmlspecialchars($client->__getLastResponse() ?? '') . "</pre>";
    }
}