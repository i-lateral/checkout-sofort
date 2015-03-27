<?php

class Sofort extends PaymentMethod {
    
    public static $handler = "SofortHandler";

    public $Title = 'Sofort';

    private static $db = array(
        "ProjectID" => "Varchar(99)",
        "ConfigKey" => "Varchar(255)"
    );

    public function getCMSFields() {
        $fields = parent::getCMSFields();

        if($this->ID) {
            $fields->addFieldToTab(
                "Root.Main",
                TextField::create('ProjectID', 'Project ID'),
                "Summary"
            );
        }

        return $fields;
    }

    public function onBeforeWrite() {
        parent::onBeforeWrite();

        $this->CallBackSlug = (!$this->CallBackSlug) ? 'Sofort' : $this->CallBackSlug;

        $this->Summary = (!$this->Summary) ? "Pay with Sofort" : $this->Summary;
    }
    
}
