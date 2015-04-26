<?php

class SofortHandler extends PaymentHandler {

    public function index($request) {
        
        $this->extend('onBeforeIndex');
        
        $site = SiteConfig::current_site_config();
        $order = $this->getOrderData();
        $cart = ShoppingCart::get();
        $key = $this->payment_gateway->ConfigKey;
        
        $sofort = new SofortMultipayPayment($key);
        $sofort->setAmount(number_format($cart->TotalCost,2));
        $sofort->setCurrencyCode(Checkout::config()->currency_code);
        
        $callback_url = Controller::join_links(
            Director::absoluteBaseURL(),
            Payment_Controller::config()->url_segment,
            "callback",
            $this->payment_gateway->ID
        );

        $success_url = Controller::join_links(
            Director::absoluteBaseURL(),
            Payment_Controller::config()->url_segment,
            'complete'
        );

        $error_url = Controller::join_links(
            Director::absoluteBaseURL(),
            Payment_Controller::config()->url_segment,
            'complete',
            'error'
        );
        
        $back_url = Controller::join_links(
            Director::absoluteBaseURL(),
            Checkout_Controller::config()->url_segment,
            "finish"
        );
        
        $sofort->setSuccessUrl($success_url, true);
        $sofort->setAbortUrl($error_url);
        $sofort->setNotificationUrl($callback_url);
        
        $desc_string = "";
        
        foreach($cart->getItems() as $item) {
            $desc_string .= $item->Title . ' x ' . $item->Quantity . ', ';
        }

        $sofort->setReason($desc_string);
        $sofort->sendRequest();

        $fields = new FieldList();

        $i = 1;

        $actions = FieldList::create(LiteralField::create(
            'BackButton',
            '<a href="' . $back_url . '" class="btn btn-red checkout-action-back">' . _t('Checkout.Back','Back') . '</a>'
        ));

        $form = Form::create($this,'Form',$fields,$actions)
            ->addExtraClass('forms')
            ->setFormMethod('GET');
            
        if($sofort->getPaymentUrl()) {
            $actions->add(
                FormAction::create(
                    'Submit',
                    _t('Checkout.ConfirmPay','Confirm and Pay')
                )->addExtraClass('btn')
                ->addExtraClass('btn-green')
            );
            
            $form->setFormAction($sofort->getPaymentUrl());
            
            // Set the Payment No to our order data (accessable by
            // onAfterIndex)
            $order->PaymentID = $sofort->getTransactionId();
        } else {
            $actions->add(LiteralField::create(
                'BackButton',
                '<strong class="error">' . _t('Sofort.TransactionError','Error with transaction') . '</strong>'
            ));
        }
        
        $this->customise(array(
            "Title"     => _t('Checkout.Summary',"Summary"),
            "MetaTitle" => _t('Checkout.Summary',"Summary"),
            "Form"      => $form,
            "Order"     => $order
        ));
        
        $this->extend("onAfterIndex");
        
        return $this->renderWith(array(
            "Sofort",
            "Payment",
            "Checkout",
            "Page"
        ));
    }

    /**
     * Process the callback data from the payment provider
     */
    public function callback($request) {
        
        $this->extend('onBeforeCallback');
        
        $data = $this->request->postVars();
        $status = "error";
        $key = $this->payment_gateway->ConfigKey;
        
        $content = file_get_contents('php://input');

        // Check if CallBack data exists and install id matches the saved ID
        if(isset($content)) {
            $notification = new SofortLibNotification();
            $transaction_id = $notification->getNotification($content);

            $sofort = new SofortLibTransactionData($key);
            $sofort->addTransaction($transaction_id);
            $sofort->sendRequest();
            
            switch($sofort->getStatus()) {
                case 'received':
                    $status = "paid";
                    break;
                case 'loss':
                    $status = "failed";
                    break;
                case 'pending':
                    $status = "pending";
                    break;
                case 'refunded':
                    $status = "refunded";
                    break;
               default:
                    $status = "error";
            }
            
            $payment_data = ArrayData::array_to_object(array(
                "OrderID" => 0,
                "PaymentID" => $notification->getTransactionId(),
                "Status" => $status,
                "GatewayData" => $data
            ));
            
            $this->setPaymentData($payment_data);
            
            $this->extend('onAfterCallback');
            
            return $this->renderWith(array(
                "Sofort_callback",
                "Checkout",
                "Page"
            ));
        }
        
        return $this->httpError(500);
    }

}
