<?php

namespace App\Utility\Sections;

use App\Utility\Schema;

class Footer
{
    use Schema;

    public $has_element = true;

    public $has_content = true;

    public $image_upload_ids = ['image_one'];

    public $fields = [
        'default' => [
            "footer_short_details" => "Textarea",
            "image_one" => "Upload"
        ],
        'light' => [
            "footer_short_details" => "Textarea",
            "image_one" => "Upload"
        ],
        'blue' => [
            "footer_short_details" => "Textarea",
            "image_one" => "Upload"
        ]
    ];

    public $classes = [
        'default' => [
            "footer_short_details" => "col-md-12",
            "image_one" => "col-md-3"
        ],
        'light' => [
            "footer_short_details" => "col-md-12",
            "image_one" => "col-md-3"
        ],
        'blue' => [
            "footer_short_details" => "col-md-12",
            "image_one" => "col-md-3"
        ]
    ];

    public $validation = [
        'default' => [
            "footer_short_details" => "required",
            "image_one" => "sometimes|image|mimes:jpg,jpeg,png|max:4096"
        ],
        'light' => [
            "footer_short_details" => "required",
            "image_one" => "sometimes|image|mimes:jpg,jpeg,png|max:4096"
        ],
        'blue' => [
            "footer_short_details" => "required",
            "image_one" => "sometimes|image|mimes:jpg,jpeg,png|max:4096"
        ]
    ];

    public $elementFields = [
        'default' => [],
        'light' => [],
        'blue' => [
            "title" => "Text",
            "description" => "Textarea",
        ]
    ];

    public $elementClasses = [
        'default' => [],
        'light' => [],
        'blue' => [
            "title" => "col-md-6",
            "description" => "col-md-12",
        ]
    ];

    public $elementValidation = [
        'default' => [],
        'light' => [],
        'blue' => [
            "title" => "required",
            "description" => "required",
        ]
    ];

}
