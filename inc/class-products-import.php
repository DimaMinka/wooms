<?php

/**
 * Import products from MoySklad
 */
class woomss_tool_products_import {

    function __construct(){
      //do_action('wooms_product_import_row', $value, $key, $data);
        add_action('wooms_product_import_row', [$this, 'load_data'], 10, 3);
    }


    function load_data($value, $key, $data){

      if( ! empty($value['archived']))
        continue;

      if( empty($value['article']))
        continue;

      $product_id = wc_get_product_id_by_sku($value['article']);

      if( intval($product_id) ){
        $this->update_product($product_id, $value);
      } else {
        $this->add_product($value);
      }

    }

    /**
     * Add product from source data
     *
     * @param $data_of_source var data of source from MoySklad
     * @return return bool - true or false if updated
     */
    function add_product($data_source){

        // $product = new WC_Product_Simple();
        $post_data = array(
          'post_type' => 'product',
          'post_title'    => wp_filter_post_kses( $data_source['name'] ),
          'post_status'   => 'draft'
        );

        // Вставляем запись в базу данных
        $post_id = wp_insert_post( $post_data );

        $product = wc_get_product($post_id);

        if( isset($data_source['article']) ){
          update_post_meta( $post_id, $meta_key = '_sku', $meta_value = $data_source['article'] );
        }

        printf('<p>+ Добавлен продукт в базу: %s</p>', $post_id);
    }

    /**
     * Update product from source data
     *
     * @param $product_id var id product
     * @param $data_of_source var data of source from MoySklad
     * @return return bool - true or false if updated
     */
    function update_product($product_id, $data_of_source){

        $product = wc_get_product($product_id);

        printf('<p><strong># data updating for product id: %s</strong> <a href="%s">(edit link)</a></p>', $product_id, get_edit_post_link($product_id, ''));

        //save data of source
        $now = date("Y-m-d H:i:s");
        update_post_meta($product_id, 'woomss_data_of_source', print_r($data_of_source, true));
        update_post_meta($product_id, 'woomss_updated_timestamp', $now);
        printf('<p>+ Save source data in meta field "woomss_data_of_source" at time: %s</p>', $now);


        if( isset($data_of_source['name']) and $data_of_source['name'] != $product->get_title() ){
          wp_update_post( array(
            'ID'          =>  $product_id,
            'post_title'  =>  $data_of_source['name']
          ));

          printf('<p>+ Update title: %s</p>', $data_of_source['name']);
        } else {
          printf('<p>- Title no updated</p>');
        }

        if( isset($data_of_source['description']) and empty($product->post->post_content) ){
          wp_update_post( array(
            'ID'          =>  $product_id,
            'post_content'  =>  $data_of_source['description']
          ));
          printf('<p>+ Update post content: %s</p>', $product_id);
        } else {
          printf('<p>- Content no updated</p>');
        }

        //Image product update or rest
        if(isset($data_of_source['image'])){
          $this->save_image_product_from_moysklad($data_of_source['image'], $product_id);
        } else {
          $this->save_image_product_from_moysklad(null, $product_id);
        }

        //Price Retail 'salePrices'
        if(isset($data_of_source['salePrices'][0]['value'])){
          $price_source = floatval($data_of_source['salePrices'][0]['value']/100);

          if($price_source != $product->get_price()){
            update_post_meta( $product->id, '_regular_price', $price_source );
            update_post_meta( $product->id, '_price', $price_source );

            printf('<p>+ Update product price: %s</p>', $price_source);
          } else {
            printf('<p>- No update product price: %s</p>', $price_source);
          }
        }

        // Update data of attributes
        if(isset($data_of_source['attributes'])){
          $this->update_attributes( $product_id, $data_of_source['attributes'] );
        }

        wp_update_post( array(
          'ID' =>  $product_id
        ));

        if(get_option('woomss_debug')){
          unset($data_of_source['description']);
          printf('<pre>%s</pre>', print_r($data_of_source, true));

        }

    }


    /**
     * Updating attributes for product
     *
     * @param $product_id - id product
     * @param $attributes - array of attributes
     * @return return type
     */
    public function update_attributes($product_id, $attributes){

      if(is_array($attributes)){
        $product = wc_get_product($product_id);

        $product_attributes_v1 = get_post_meta($product_id, '_product_attributes', true);
        $product_attributes_v2 = array();

        foreach ($attributes as $key => $attribute) {
          //Type attribute
          $product_attributes_v2[$attribute['id']] = array(
              //Make sure the 'name' is same as you have the attribute
              'name' => htmlspecialchars(stripslashes($attribute['name'])),
              'value' => $attribute['value'],
              'position' => 0,
              'is_visible' => 0,
              'is_variation' => 0,
              'is_taxonomy' => 0
          );
        }

        if($product_attributes_v1 != $product_attributes_v2) {
          //Add as post meta
          update_post_meta($product_id, '_product_attributes', $product_attributes_v2);

          printf('<p>+ Updated product attributes: %s</p>', count($product_attributes_v2));
        } else {
          printf('<p>- Product attributes not updated: %s</p>', count($product_attributes_v1));

        }

      } else {
        delete_post_meta( $product_id, '_product_attributes' );
        printf('<p>+ Deleted attributes for product</p>');

      }

      return true;
    }

} new woomss_tool_products_import;