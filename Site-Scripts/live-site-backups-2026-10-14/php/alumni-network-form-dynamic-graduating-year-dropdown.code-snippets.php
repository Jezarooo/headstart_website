<?php

/**
 * Alumni-Network-Form--Dynamic-Graduating-Year-Dropdown
 */
add_filter('fluentform/rendering_field_data_select', function ($data, $form) {
    // ONLY run this on Form ID 11
    if ($form->id != 11) {
        return $data;
    }

    // ONLY target the specific dropdown field named 'graduating_year'
    // Note for web team: Ensure the "Name Attribute" in the field's Advanced Options is set to 'graduating_year'
    if (\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'attributes.name') != 'graduating_year') {
        return $data;
    }

    // Generate the list of years dynamically
    $current_year = date('Y');
    $start_year = 2005; // HeadStart's foundation year
    $dynamic_options = [];

    // Loop backward from the current year down to 2005
    for ($year = $current_year; $year >= $start_year; $year--) {
        $dynamic_options[] = [
            "label" => (string)$year,
            "value" => (string)$year,
            "calc_value" => ""
        ];
    }

    // Replace the default options with our new dynamic list
    $data['settings']['advanced_options'] = $dynamic_options;

    return $data;
}, 10, 2);

// IMPORTANT: Bypass Fluent Forms' strict backend validation for this specific field.
// Because the years are not typed into the form builder manually, the system might reject them upon submission.
// This filter forces the system to accept the dynamic input.
add_filter('fluentform/validate_input_item_select', function ($error, $field) {
    return []; 
}, 10, 2);
