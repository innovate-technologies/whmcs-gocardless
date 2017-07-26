<?php
/*
Copyright (c) 2017, Maartje Eyskens at The Innovating Group LLP
All rights reserved.
*/

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
require "gocardless/vendor/autoload.php";

function gocardless_MetaData()
{
    return array(
        'DisplayName' => 'GoCardless Gateway Module',
        'APIVersion' => '1.0', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => true,
    );
}

function gocardless_config() {
    return [
        'FriendlyName' => ['Type' => 'System', 'Value' => 'GoCardless'],
        'accessToken' => ['FriendlyName' => 'Access Token', 'Type' => 'text', 'Size' => '40'],
        'webhookSignature' => ['FriendlyName' => 'Webhook Signature', 'Type' => 'text', 'Size' => '40'],
        'sandbox' => ["FriendlyName" => "Sandbox", "Type" => "yesno"]
    ];
}

function getConfiguredGoCardless($params) {
     return new \GoCardlessPro\Client(array(
        'access_token' => $params['accessToken'],
        'environment'  => ($params['sandbox'] === 'on') ? \GoCardlessPro\Environment::SANDBOX : \GoCardlessPro\Environment::LIVE 
    ));
}

function gocardless_link($params) {
    $gocardless = getConfiguredGoCardless($params);
    $output = "";
    $hasMandate = false;

    if (!gocardless_checkCurrency($params)) {
        return "You currency is currently not supported";
    }

    $userInfo = Capsule::table('tblclients')
        ->where('id', $params['clientdetails']["userid"])
        ->get();
    
    if (!empty($userInfo[0]->gatewayid) && $userInfo[0]->cardtype == "GoCardless") {
        $hasMandate = true;
        $output .= '<a href="' .  $params['systemurl'] . '/modules/gateways/callback/gocardless.php?paynow=true' . '"><button class="btn btn-success">' . $params["langpaynow"] . '</button></a></br></br>';
    }
    
    $_SESSION["gc_session_token"] = $token = bin2hex(openssl_random_pseudo_bytes(64)); // generate randon secure token
    $_SESSION["gc_params"] = $params;

    $redirectFlow = $gocardless->redirectFlows()->create(
        [
            "params" => [
                "session_token" => $_SESSION["gc_session_token"],
                "success_redirect_url" => $params['systemurl'] . '/modules/gateways/callback/gocardless.php',
                "prefilled_customer" => [
                    "given_name" => $params['clientdetails']['firstname'],
                    "family_name" => $params['clientdetails']['lastname'],
                    "email" => $params['clientdetails']['email'],
                    "address_line1" => $params['clientdetails']['address1'],
                    "address_line2" => $params['clientdetails']['address2'],
                    "city" => $params['clientdetails']['city']
                ]   
            ]
        ]
    );

    $output .= '<a href="' . $redirectFlow->redirect_url . '"><button class="btn btn-primary">' . ($hasMandate ? "Re-Setup" : "Setup") .' Direct Debit</button></a>';
    return $output;
}


function gocardless_capture($params) {
    if (!gocardless_checkCurrency($params)) {
        return array('status' => 'error', 'rawdata' => "Currency not supported");
    }

    $gocardless = getConfiguredGoCardless($params);
    
    $userInfo = Capsule::table('tblclients')
        ->where('id', $params['clientdetails']["userid"])
        ->get();
    if (empty($userInfo[0]->gatewayid) || $userInfo[0]->cardtype != "GoCardless") {
        return array('status' => 'error', 'rawdata' => "No mandate set-up");
    }

    $token = bin2hex(openssl_random_pseudo_bytes(64));
    try {
        $payment = $gocardless->payments()->create([
            "params" => [
                "amount" => floatval($params['amount']) * 100,
                "currency" => $params['currency'],
                "links" => [
                    "mandate" => $userInfo[0]->gatewayid
                ],
                "metadata" => [
                    "invoice_number" => (string)$params['invoiceid']
                ]
            ],
            "headers" => [
                "Idempotency-Key" => $token
            ]
        ]);

        return array(
            'status' => 'success',
            'rawdata' => $payment,
            'transid' => $payment->id,
            'fees' => gocardless_getFee($params['amount']),
        );
    } catch($e) {
        return array('status' => 'error', 'rawdata' => $e);
    }
}

// Who knows if this is still in use, but it should make the system clear we don't use cards here.
function gocardless_nolocalcc() {}


function gocardless_refund($params) {
    $gocardless = getConfiguredGoCardless($params);
    $payment = $gocardless->payments()->cancel($params['transid']);
    return array(
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $payment
    );
}

function gocardless_process_mandate_event($event) {
    switch ($event["action"]) {
        case "failed":
        case "expired":
        case "resubmission_requested":
        case "cancelled":
            print("Removing " . $event["links"]["mandate"] . "!\n");

            Capsule::table('tblclients')
                ->where('gatewayid', $event["links"]["mandate"])
                ->update(['gatewayid' => '']);
            break;
        default:
            print("Don't know how to process a mandate " . $event["action"] . " event\n");
            break;
  }
}

function gocardless_process_payment_event($event) {
    $gocardless = getConfiguredGoCardless(getGatewayVariables("gocardless"));
    switch ($event["action"]) {
        case "failed":
        case "charged_back":
            print("Removing " . $event["links"]["payment"] . "!\n");
            $transactions = localAPI("GetTransactions", ["transid" => $event["links"]["payment"]], "API");
            if (count($transactions["transactions"]["transaction"]) < 1) {
                return;
            }

            localAPI("UpdateTransaction", [
                'transactionid' => $transactions["transactions"]["transaction"][0]["id"],
                'amountin' => 0.0,
                'fees' => 0.0
            ], "API");

            localAPI("UpdateInvoice", array(
                'invoiceid' => $transactions["transactions"]["transaction"][0]["invoiceid"],
                'status' => 'Unpaid'
            ), "API");

            break;
        default:
            print("Don't know how to process a mandate " . $event["action"] . " event\n");
            break;
  }
}

function gocardless_checkCurrency($params) {
     if (!in_array($params['currency'], ["GBP", "EUR", "SEK"])) {
        return false;
    }
    return true;
}

function gocardless_getFee($amount) {
    $fee = $amount / 100 * 1;
    if ($fee < 0.20) {
        $fee = 0.20;
    } else if ($fee > 2) {
        $fee = 2;
    }

    return $fee;
}