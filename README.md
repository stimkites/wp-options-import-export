# WordPress and WooCommerce options import/export
WP and Woo options import/export

#### Menu
To run export or import use native WP importer/exporter approach:

-   Tools -> Export -> Select options to export
-   Tools -> Import -> Run WP Optimex importer

### JSON readable format
There are several formats available for importing.

-   Native

Here "key" is the value hashed with md5() - ``` md5( '@jduWOn)E39(*W-=871)W54+06kld' )```.
Key is used to verify the import file.
```
{
    "key": "KEY_VALUE",
    "wp_options": [
        {
            "option_name": "blogname",
            "option_value": "Reschia",
            "autoload": "yes"
        },
        ...
    ],
    "woo_options": [
        {
            "option_name": "some_option",
            "option_value": "value",
            "autoload": "yes"
        },
        ...
    ],
}
```

- Alternative
```
[
    {
        "option_name": "blogname",
        "option_value": "Reschia",
        "autoload": "yes"
    },
    {
        "option_name": "some_option",
        "option_value": "value",
        "autoload": "yes"
    },
    ...
]
```

- Associative
```
{
    "option_name": "value",
    "option_name": "value",
    "option_name": "value",
    "option_name": "value",
    ...
]
```

### Version log
- 0.0.1
    - Initial version
