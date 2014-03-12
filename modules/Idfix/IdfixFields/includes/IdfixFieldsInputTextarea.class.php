<?php

class IdfixFieldsInputTextarea extends IdfixFieldsInput
{

    public function GetEdit()
    {
       $this->IdfixDebug->Profiler(__method__, 'start');
        // Unique CSS ID
        $cId = $this->GetId();
        // Unique form input element name
        $cName = $this->GetName();
        // Get CSS class for the input element
        $this->SetCssClass('form-control');
        $this->SetDataElement('id', $cId);
        $this->SetDataElement('name', $cName);

        // Set the value
        $cValue = $this->Clean( $this->GetValue() );

        // Build the attributelist
        $cAttr = $this->GetAttributes($this->aData);

        // And get a reference to the input element
        $cInput = "<textarea {$cAttr}>{$cValue}</textarea>";

        // Wrap the element in a group if it is required
        //$cInput = $this->WrapRequired($cInput);

        // Get any validation messages
        $cError = $this->Validate();


        $this->aData['__DisplayValue'] = $this->RenderFormElement($this->aData['title'], $this->aData['description'], $cError, $cId, $cInput);
        $this->IdfixDebug->Profiler(__method__, 'stop');
    }

}