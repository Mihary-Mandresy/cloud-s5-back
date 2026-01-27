<?php

return [
    'default' => 'default',
    
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'API Authentication',
                'version' => '1.0.0',
                'description' => 'Documentation de l\'API d\'authentification',
            ],
            'routes' => [
                'api' => 'api/documentation',
                'docs' => 'docs',
                'oauth2_callback' => 'api/oauth2-callback',
            ],
            'paths' => [
                'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
                'annotations' => [
                    base_path('app/Http/Controllers'),
                ],
            ],
        ],
    ],
    
    'defaults' => [
        'routes' => [
            'api' => 'api/documentation',
            'docs' => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],
            'group_options' => [],
        ],
        
        'paths' => [
            'use_absolute_path' => true,
            'docs' => storage_path('api-docs'),
            'annotations' => [
                base_path('app/Http/Controllers'),
            ],
            'excludes' => [],  
            'base' => env('APP_URL', 'http://localhost:8000'),
            'docs_json' => 'api-docs.json',
            'docs_yaml' => 'api-docs.yaml',
            'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
            'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
        ],
        
        'scanOptions' => [
            'analyser' => null,
            'analysis' => null,
            'processors' => [],
            'pattern' => null,
            'exclude' => [],
        ],
        
        'securityDefinitions' => [
            'securitySchemes' => [
                /*
                'api_key_security_example' => [
                    'type' => 'apiKey',
                    'name' => 'api_key',
                    'in' => 'header',
                ],
                'oauth2_security_example' => [
                    'type' => 'oauth2',
                    'flow' => 'password',
                    'tokenUrl' => '/oauth/token',
                    'scopes' => [],
                ],
                */
            ],
        ],
        'proxy' => false, 
        'additional_config_url' => null,
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
        'validator_url' => null,
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', true),
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
        
        'ui' => [
            'display' => [
                'doc_expansion' => 'none',
                'filter' => true,
            ],
        ],
    ],
];