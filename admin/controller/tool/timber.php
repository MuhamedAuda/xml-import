<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class ControllerToolTimber extends Controller
{
    public function index()
    {
        $this->load->model('tool/timber');
        $this->add();
    }

    public function add()
    {
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/option');
        $this->load->model('catalog/product');
        $this->load->model('localisation/tax_class');
        $this->load->model('localisation/tax_rate');

        // 1. loading xml file
        $path_to_files = str_replace("admin/", "", DIR_APPLICATION) . "products/xml/";
        $files = glob($path_to_files . "*xml");
        if (is_array($files)) {
            foreach ($files as $filename) {
                if (file_exists($filename)) {
                    $xml_query = simplexml_load_file($filename) or die("Error: Cannot create object");
                    if (!$xml_query) {
                        echo 'Error while loading XML file <br />';
                    } else {
                        echo 'XML file loaded successfully  <br />';
                    }
                } else {
                    echo 'The file at: ' . $filename . ' Not exist please check it. <br />';
                    die();
                }
                $counter = 1;
                foreach ($xml_query->product as $product) {
                    echo 'start proccessing product: ' . $counter . '  <br />';
                    $data = array();
                    $data['product_description'] = array(
                        '1' => array(
                            'name' => (string)$product->name,
                            'description' => (string)$product->description,
                            'meta_title' => (string)$product->name,
                            'meta_description' => (string)$product->description,
                            'meta_keyword' => '',
                            'tag' => ''
                        )
                    );
                    $data['model'] = (string)$product->key;
                    $data['sku'] = '';
                    $data['upc'] = '';
                    $data['ean'] = '';
                    $data['jan'] = '';
                    $data['isbn'] = '';
                    $data['mpn'] = '';
                    $data['location'] = '';

                    // ******************* Calculation  price **************
                    $product_dim = explode("mm", $product->description);
                    $dim1 = floatval(substr($product_dim[0], -2)) / 100; // ex 75mm
                    $dim2 = floatval(substr($product_dim[1], -2)) / 100; // ex 44mm
                    $data['price'] = floatval($product->price) * $dim1 * $dim2;

                    // ********esponi price Formula ****** Must be processed after options
                    $data['esponi_priceFormulaActive'] = '1';
                    $data['esponi_priceFormula'] = '';

                    // ******************* Processing tax rate **************
                    // 1.Tax rate exist
                    $tax_rate_id = $this->model_tool_timber->getTaxRateByRateType($product->vat->percentage);
                    if ($tax_rate_id != 0) {
                        // @todo check if tax class id is exist first.
                        $tax_class_id = $this->model_tool_timber->getTaxClassByRateId($tax_rate_id);
                        echo '<br /> Tax rate: ' . $tax_class_id . ' exist.';
                    } else {
                        echo '<br /> Tax rate Not exist.';
                        // 2.Tax rate not exist
                        $config_geo_zone_id = 5;
                        $tax_rate_data = array(
                            'name' => $product->vat->rate,
                            'rate' => $product->vat->percentage,
                            'type' => 'P',
                            'tax_rate_customer_group' => array(
                                '0' => 1
                            ),
                            'geo_zone_id' => $config_geo_zone_id
                        );
                        $tax_rate_id = $this->model_localisation_tax_rate->addTaxRate($tax_rate_data);

                        $tax_class_id = $this->model_tool_timber->getTaxClassByRateId($tax_rate_id);
                        if ($tax_class_id == 0) {
                            $tax_class_data = array(
                                'title' => $product->vat->rate . " Class",
                                'description' => $product->vat->rate . " Class",
                                'tax_rule' => array(
                                    '0' => array(
                                        'tax_rate_id' => $tax_rate_id,
                                        'based' => 'shipping',
                                        'priority' => 0,
                                    )
                                )
                            );
                            $tax_class_id = $this->model_localisation_tax_class->addTaxClass($tax_class_data);
                        }
                    }

                    $data['tax_class_id'] = $tax_class_id;
                    // ***end processing tax rate and class.***

                    $data['quantity'] = 10000; // @todo check this with mike if static or calculated
                    $data['minimum'] = 1;
                    $data['subtract'] = 1;
                    $data['stock_status_id'] = 5;
                    $data['shipping'] = 1;
                    $data['date_available'] = '2020 - 01 - 01';
                    $data['length'] = '';
                    $data['width'] = '';
                    $data['height'] = '';
                    $data['length_class_id'] = 1;
                    $data['weight'] = '';
                    $data['weight_class_id'] = 1;
                    $data['status'] = 1;
                    $data['sort_order'] = 1;

                    // processing manufacturers
                    if ($this->model_tool_timber->checkExistManufacturer($product->supplier) != 0) {
                        echo '<br /> manufacturer exist.';
                        $manufacturer_info = $this->model_tool_timber->getManufacturerByName($product->supplier);
                        $manufacturer_id = $manufacturer_info['manufacturer_id'];
                    } else {
                        echo '<br /> add new  manufacturer.';
                        $manufacturer_id = $this->model_catalog_manufacturer->addManufacturer(array(
                            'name' => $product->supplier,
                            'sort_order' => 0
                        ));
                    }
                    $data['manufacturer_id'] = $manufacturer_id;
                    $data['manufacturer'] = '';

                    $data['category'] = array( //@category static or dynamic
                        0 => '25',
                    );
                    $data['filter'] = '';
                    $data['product_store'] = array(
                        0 => '0',
                    );
                    $data['download'] = '';
                    $data['related'] = '';
                    $data['option'] = 'tex';
                    // ********** Adding product options **************
                    // 1.check if option is exist in store first or add option to store
                    //  name mask: 3.6M/€24.26
                    $option_counter = 1;
                    foreach ($product->timber->length as $element) {
                        echo '<br /> Start processing option: ' . $option_counter;

                        $option_name = rtrim($element->name, '0.') . 'M/€' . round($element->price, 2);
                        $option_id = $this->model_tool_timber->checkExistOptionByName($option_name);
                        $option_data = array(
                            'option_description' => array(
                                '1' => array('name' => $option_name)
                            ),
                            'type' => 'text',
                            'sort_order' => 0
                        );

                        if ($option_id == 0) { // option not exist, add it, then add it to new products option list
                            echo '<br /> option not exist add it: ' . $option_counter;

                            $option_id_added = $this->model_catalog_option->addOption($option_data);

                            $data['product_option'][] = array(
                                'product_option_id' => '',
                                'name' => $option_name,
                                'option_id' => $option_id_added,
                                'type' => 'text',
                                'required' => '0',
                                'value' => ''
                            );
                        } else { // option is exist , get id and add it to new product options list
                            echo '<br /> option exist get it: ' . $option_counter;

                            $data['product_option'][] = array(
                                'product_option_id' => '',
                                'name' => $option_name,
                                'option_id' => $option_id,
                                'type' => 'text',
                                'required' => '0',
                                'value' => ''
                            );
                        }
                        $option_counter++;
                    } // end looping options in xml file

                    // ******* processing product image, copy from remote url
                    if (!file_exists(DIR_IMAGE . basename($product->image))) {
                        $ch = curl_init($product->image);
                        $fp = fopen(DIR_IMAGE . basename($product->image), 'wb');
                        curl_setopt($ch, CURLOPT_FILE, $fp);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_exec($ch);
                        curl_close($ch);
                        fclose($fp);
                        echo 'Image coppied to image folder sccessfully to: ' . DIR_IMAGE . basename($product->image) . '<br />';
                    } else {
                        echo 'Image already exist at image folder at ' . DIR_IMAGE . basename($product->image) . '<br />';
                    }

                    $data['image'] = 'catalog/' . basename($product->image);

                    $data['points'] = '';
                    //$data['product_reward'] = '';
                    $data['product_seo_url'][] = array(
                        '1' => '',
                    );
                    $counter++;

                    // start adding products to database.
                    $product_id = $this->model_tool_timber->checkExistProduct($xml_query->product->key);
                    if ($product_id == 0) {
                        $product_id_info = $this->model_catalog_product->addProduct($data);
                        echo '<br /> New product: ' . $product_id_info . ' added.';
                    } else {
                        $product_id_info = $this->model_catalog_product->editProduct($product_id, $data);
                        echo '<br /> Existing product: ' . $product_id . ' updated.';
                    }
                } // end xml_query
            }
        }
    }


}