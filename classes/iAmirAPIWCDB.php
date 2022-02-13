<?php


class iAmirAPIWCDB
{
    public $db = null;
    public $groups = null;

    public function __construct()
    {
        if (get_option("i_amir_remote_wc_db_username") && get_option("i_amir_remote_wc_db_password") && get_option("i_amir_remote_wc_db_name") && get_option("i_amir_remote_wc_db_host"))
            $this->db = new wpdb(get_option("i_amir_remote_wc_db_username"),get_option("i_amir_remote_wc_db_password"),get_option("i_amir_remote_wc_db_name"), get_option("i_amir_remote_wc_db_host"));
    }

    public function __call($name, $arguments)
    {
        if (!$this->db || ($this->db && !$this->db->check_connection())) return false;
        if (!method_exists($this, $name)) return $this->db->$name(...$arguments);
        return $this->$name(...$arguments);
    }

    public function query($query)
    {
        return $this->get_results($query);
    }

    public function getGroups()
    {
        if ($this->groups != null) return $this->groups;
        $groups = get_option("i_amir_remote_wc_groups");
        $query = "select * from extrafield" . ($groups ? (" where extrafield.id in (". join(",", $groups).")") : "");
        return $this->groups = ($this->get_results($query) ? : []);
    }

    public function findGroupByCB($category, $brand) {
        if ($this->groups == null) $this->getGroups();
        $items = array_values(array_filter($this->groups, function ($item) use ($category, $brand) {
            return intval($item->category_id) == intval($category) && intval($item->brand_id) == intval($brand);
        }));
        if(!count($items)) return false;
        $item = $items[0];
        if ($item->data && is_string($item->data) && strlen($item->data) > 5) $item->data = json_decode($item->data);
        return $item;
    }

    public function lastTask()
    {
        $query = 'select * from task where JSON_EXTRACT(extra, "$.product_skus") is not null and state != \'error\' order by id DESC limit 1;';
        if (!($item = $this->get_row($query))) return false;
        $item->extra = json_decode($item->extra);
        return $item;
    }

    public function createTask($extra, $task = "scrap_products_update", $task_id = 5)
    {
        $datetime = new DateTime("now", new DateTimeZone("Asia/Tehran"));
        $datetime = $datetime->format("Y-m-d H:i:s");
        $hash = md5("scrap_products_update:" . (is_array($extra) ? json_encode($extra) : $extra));
        $data = ['task_id' => $task_id, 'hash' => (string) $hash, "extra" => (is_array($extra) ? json_encode($extra) : $extra), "state" => "pending", "start_ts" => $datetime, "end_ts" => $datetime];
        $keys = join(",", array_map(function ($key) { return "`$key`"; } , array_keys($data)));
        $values = join(",", array_map(function ($key) { return "'$key'"; } , array_values($data)));
        $query = "INSERT INTO `task`($keys) VALUES ($values)";
        $this->db->get_row($query);
        return true;
    }

    public function getSkus($sid = 0, $limit = 100)
    {
        global $wpdb;
        $result = $wpdb->get_results("select meta_value from {$wpdb->prefix}postmeta left join {$wpdb->prefix}posts on {$wpdb->prefix}posts.id = {$wpdb->prefix}postmeta.post_id where {$wpdb->prefix}postmeta.post_id > $sid and meta_key = '_sku' and meta_value is not null and {$wpdb->prefix}posts.post_type = 'product' order by {$wpdb->prefix}postmeta.post_id limit $limit;", ARRAY_A);
        return array_column($result, 'meta_value');
    }

    public function getProducts($sid = 0, $limit = 50, $groups = null)
    {
        $query = "select *,{$this->buildQueryGroupCol()},{$this->buildQueryProductCol()},{$this->buildQueryVariantsCol()},{$this->buildQueryImagesCol()} from products_groups where products_groups.product_id > $sid and products_groups.group_id in (". $this->buildQueryGroups($groups) .") group by product_id order by product_id limit $limit;";
        return $this->get_results($query);
    }

    public function productBySku($sku)
    {
        $query = "select *,{$this->buildQueryGroupCol()},{$this->buildQueryProductCol()},{$this->buildQueryVariantsCol()},{$this->buildQueryImagesCol()} from products_groups where products_groups.product_id = (select pps.id from product as pps where pps.sku = $sku) order by product_id limit 1;";
        return $this->get_row($query);
    }

    public function buildQueryProductCol() {
        return "(select json_object('id',`product`.`id`,'name',`product`.`name`,'sku',`product`.`sku`,'description',`product`.`description`,'rating_average',`product`.`rating_average`) from product where products_groups.product_id = product.id limit 1) as product";
    }

    public function buildQueryGroupCol() {
        return "(select json_object('category_id',`product_group`.`category_id`,'brand_id',`product_group`.`brand_id`) from product_group where products_groups.group_id = product_group.id limit 1) as product_group";
    }

    public function buildQueryVariantsCol() {
        return "(select json_arrayagg(json_object('id',`product_variant`.`id`,'barcode',`product_variant`.`barcode`,'sku',`product_variant`.`sku`,'stock',`product_variant`.`stock`,'price_selling',`product_variant`.`price_selling`,'price_original',`product_variant`.`price_original`,'attribute', (select json_object('id', `product_attribute`.`id`, 'type', `product_attribute`.`type`, 'value', `product_attribute`.`value`) from `product_attribute` where `product_attribute`.`id` = `product_variant`.`attribute_id` limit 1))) from product_variant where products_groups.product_id = product_variant.product_id) as variants";
    }

    public function buildQueryImagesCol() {
        return "(select json_arrayagg(json_object('id',`product_image`.`id`,'url',`product_image`.`url`)) from product_image left join products_images pi on product_image.id = pi.image_id where pi.product_id = products_groups.product_id) as images";
    }

    public function buildQueryGroups($groups = null) {
        if (!$groups) $groups = $this->getGroups();
        $groupsQ = join(" OR ", array_map(function ($group) {return "({$this->buildQueryGroup($group->category_id, $group->brand_id)})";}, $groups));
        return "select product_group.id from product_group" . (strlen($groupsQ) > 5 ? " where $groupsQ" : "");
    }

    public function buildQueryGroup($category, $brand) {
        return "product_group.category_id = '$category' AND product_group.brand_id = '$brand'";
    }
}

