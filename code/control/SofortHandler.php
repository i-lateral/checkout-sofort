<?php

class SofortHandler extends PaymentHandler {

    public function index() {
        $site = SiteConfig::current_site_config();
        $data = $this->order_data;
        $order = ArrayData::create($data);
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
            
            /**
             * @todo remove this and rewrite the payment process so that
             * we more easily edit orders.
             */
            if(class_exists("Order")) {
                $order_object = Order::get()
                    ->filter("OrderNumber", $data["OrderNumber"])
                    ->first();
                $order_object->PaymentNo = $sofort->getTransactionId();
                $order_object->write();
            }
        } else {
            $actions->add(LiteralField::create(
                'BackButton',
                '<strong class="error">' . _t('Sofort.TransactionError','Error with transaction') . '</strong>'
            ));
        }

        $this->extend('updateForm',$form);
        
        $this->parent_controller->setOrder($order);
        $this->parent_controller->setPaymentForm($form);

        return array(
            "Title"     => _t('Checkout.Summary',"Summary"),
            "MetaTitle" => _t('Checkout.Summary',"Summary")
        );
    }

    /**
     * Process the callback data from the payment provider
     */
    public function callback() {
        $data = $this->request->postVars();
        $status = "error";
        $key = $this->payment_gateway->ConfigKey;
        
        $content = file_get_contents('php://input');

        // Check if CallBack data exists and install id matches the saved ID
        if(isset($content)) {
            $notification = new SofortLibNotification();

            $sofort = new SofortLibTransactionData($key);
            $sofort->addTransaction($notification->getNotification($content));
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
            
            $return = array(
                "Status" => $status,
                "GatewayData" => $data
            );
            
            if(class_exists("Order")) {
                $order = Order::get()
                    ->filter("PaymentNo", $notification->getTransactionId())
                    ->first();
                    
                $return["OrderID"] = $order->ID;
            }
            
            return $return;
        }
        
        return false;
    }

}
