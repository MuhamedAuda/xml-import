<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class ModelToolTimber extends Model
{
    // check if product exists by product_key in model filed ..
    public function checkExistProduct($product_key)
    {
        $query = $this->db->query("SELECT * FROM `oc_product` WHERE `model` = '" . $product_key . "' limit 1");
        if ($query->num_rows) {
            return $query->row['product_id'];
        }
        return $query->num_rows;
    }

    public function checkExistManufacturer($supplier_name)
    {
        $query = $this->db->query("SELECT * FROM `oc_manufacturer` WHERE `name` = '" . $supplier_name . "'");
        return $query->num_rows;
    }

    public function getManufacturerByName($manufacturer_name)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "manufacturer WHERE name = '" . $manufacturer_name . "' limit 1");

        return $query->row;
    }

    public function getTaxRateByRateType($rate)
    {
        $query = $this->db->query("SELECT DISTINCT tax_rate_id FROM " . DB_PREFIX . "tax_rate WHERE rate = '" . $rate . "' and `type` = 'p'  limit 1");
        if ($query->num_rows != 0) {
            return $query->row['tax_rate_id'];
        }
        return $query->num_rows;
    }

    public function getTaxClassByRateId($tax_rate_id)
    {
        $query = $this->db->query("SELECT DISTINCT tax_class_id FROM " . DB_PREFIX . "tax_rule WHERE tax_rate_id = '" . $tax_rate_id . "' limit 1");
        if ($query->num_rows != 0) {
            return $query->row['tax_class_id'];
        }
        return $query->num_rows;
    }


    public function checkExistOptionByName($option_data)
    {
        $query = $this->db->query("SELECT option_id FROM " . DB_PREFIX . "option_description WHERE `name` = '" . $option_data . "' limit 1");
        if ($query->num_rows) {
            return $query->row['option_id'];
        }

        return $query->num_rows;
    }


}