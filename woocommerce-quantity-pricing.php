<?php
/*
Plugin Name: WooCommerce Quantity Pricing
Plugin URI: http://yourwebsite.com/
Description: Adds quantity-based pricing to WooCommerce products.
Version: 1.3
Author: TouchVapes
Author URI: http://yourwebsite.com/
*/

// Add the quantity pricing tab
// This function adds a new tab to the product data meta box when editing products
function custom_product_tabs($tabs)
{
    $tabs['quantity_pricing'] = array(
        'label' => __('Precios por cantidad', 'woocommerce'),
        'target' => 'quantity_pricing_data',
        'class' => array('show_if_simple', 'show_if_variable', 'show_if_grouped'),
    );

    // New tab for special user prices
    $tabs['user_prices'] = array(
        'label' => __('Precios especiales de usuarios', 'woocommerce'),
        'target' => 'user_prices_data',
        'class' => array('show_if_simple', 'show_if_variable', 'show_if_grouped'),
    );

    return $tabs;
}
add_filter('woocommerce_product_data_tabs', 'custom_product_tabs');

// Content of the quantity pricing tab and special user prices tab
// This function generates the content of the new tab added above
function custom_product_panels()
{
    global $post;

    $custom_price_table = get_post_meta($post->ID, 'custom_price_table', true);
    $user_prices_table = get_post_meta($post->ID, 'user_prices_table', true);

    // Content of the quantity pricing tab
    echo '<div id="quantity_pricing_data" class="panel woocommerce_options_panel hidden">';
    echo '<table class="form-table" id="quantity_pricing_table">';
    echo '<thead><tr><th>Desde</th><th>Hasta</th><th>Precio</th><th>Acción</th></tr></thead>';
    echo '<tbody>';

    if (!empty($custom_price_table)) {
        foreach ($custom_price_table as $index => $row) {
            echo '<tr>';
            echo '<td><input type="text" name="custom_price_table[' . $index . '][from_quantity]" value="' . $row['from_quantity'] . '"></td>';
            echo '<td><input type="text" name="custom_price_table[' . $index . '][to_quantity]" value="' . $row['to_quantity'] . '"></td>';
            echo '<td><input type="text" name="custom_price_table[' . $index . '][price]" value="' . $row['price'] . '"></td>';
            echo '<td><button type="button" class="remove_row_button button">Eliminar</button></td>';
            echo '</tr>';
        }
    } else {
        // Show an empty row by default
        echo '<tr>';
        echo '<td><input type="text" name="custom_price_table[0][from_quantity]"></td>';
        echo '<td><input type="text" name="custom_price_table[0][to_quantity]"></td>';
        echo '<td><input type="text" name="custom_price_table[0][price]"></td>';
        echo '<td><button type="button" class="remove_row_button button">Eliminar</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<button type="button" class="add_row_button_qty button">Agregar Fila</button>';
    echo '</div>';

    // Content of the special user prices tab
    echo '<div id="user_prices_data" class="panel woocommerce_options_panel hidden">';
    echo '<table class="form-table" id="user_prices_table">';
    echo '<thead><tr><th>Usuario</th><th>Precio especial</th><th>Acción</th></tr></thead>';
    echo '<tbody>';

    if (!empty($user_prices_table)) {
        foreach ($user_prices_table as $index => $row) {
            echo '<tr>';
            echo '<td><input type="text" name="user_prices_table[' . $index . '][user_email]" value="' . $row['user_email'] . '"></td>';
            echo '<td><input type="text" name="user_prices_table[' . $index . '][price]" value="' . $row['price'] . '"></td>';
            echo '<td><button type="button" class="remove_row_button button">Eliminar</button></td>';
            echo '</tr>';
        }
    } else {
        // Show an empty row by default
        echo '<tr>';
        echo '<td><input type="text" name="user_prices_table[0][user_email]"></td>';
        echo '<td><input type="text" name="user_prices_table[0][price]"></td>';
        echo '<td><button type="button" class="remove_row_button button">Eliminar</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<button type="button" class="add_row_button_user button">Agregar Fila</button>';
    echo '</div>';
}
add_action('woocommerce_product_data_panels', 'custom_product_panels');


// Save the values of the price table field when the product is saved
// This function saves the special user prices when the product is saved
function save_user_prices_field($post_id)
{
    if (isset($_POST['user_prices_table'])) {
        $user_prices_table = $_POST['user_prices_table'];
        $new_user_prices_table = array();

        foreach ($user_prices_table as $row) {
            $user_email = sanitize_text_field($row['user_email']);
            $price = sanitize_text_field($row['price']);

            if (!empty($user_email) || !empty($price)) {
                $new_user_prices_table[] = array(
                    'user_email' => $user_email,
                    'price' => $price,
                );
            }
        }

        update_post_meta($post_id, 'user_prices_table', $new_user_prices_table);
    } else {
        delete_post_meta($post_id, 'user_prices_table');
    }
}
add_action('woocommerce_process_product_meta', 'save_user_prices_field');

// Adjust the price in the cart according to the total product quantity and the price table
// This function adjusts the price of the product in the cart based on the quantity of the product and the price table
function adjust_price_based_on_quantity($cart)
{
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];

        $total_quantity = 0;
        foreach ($cart->get_cart() as $item) {
            if ($item['product_id'] === $product_id) {
                $total_quantity += $item['quantity'];
            }
        }

        $custom_price_table = get_post_meta($product_id, 'custom_price_table', true);

        if (!empty($custom_price_table) && ($cart_item['data']->is_type('simple') || $cart_item['data']->is_type('variation'))) {
            $best_price = null;

            foreach ($custom_price_table as $row) {
                if ($total_quantity >= $row['from_quantity'] && ($row['to_quantity'] === '' || $total_quantity <= $row['to_quantity'])) {
                    if ($best_price === null || $row['price'] < $best_price) {
                        $best_price = $row['price'];
                    }
                }
            }

            if ($best_price !== null) {
                $cart_item['data']->set_price($best_price);
            }
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'adjust_price_based_on_quantity');


// Adjust the price in the cart according to the user and the price table
// This function adjusts the price of the product in the cart based on the user and the price table
function adjust_price_based_on_user($cart)
{
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $user = wp_get_current_user();
        $user_email = $user->user_email;

        $user_prices_table = get_post_meta($product_id, 'user_prices_table', true);

        if (!empty($user_prices_table) && ($cart_item['data']->is_type('simple') || $cart_item['data']->is_type('variation'))) {
            foreach ($user_prices_table as $row) {
                if ($row['user_email'] === $user_email) {
                    $price = $row['price'];
                    $cart_item['data']->set_price($price);
                    break;
                }
            }
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'adjust_price_based_on_user');



// Display quantity pricing table on the front-end
// This function displays the quantity pricing table on the product page
function display_quantity_pricing_table()
{
    global $product;

    $custom_price_table = get_post_meta($product->get_id(), 'custom_price_table', true);

    if (!empty($custom_price_table)) {
        // Add the styles here
        echo '<style>
        .bulk_table {
            margin-top: 20px;
        }

        .wdp_pricing_table_caption {
            color: #1e73be;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            font-family: "Lato", sans-serif;
        }

        .wdp_pricing_table {
            border-collapse: collapse;
            font-size: 0.9em;
            table-layout: fixed;
            width: 100%;
        }

        .wdp_pricing_table th,
        .wdp_pricing_table td {
            border: 1px solid #dfdfdf;
            padding: 5px;
            text-align: center;
        }

        .wdp_pricing_table th {
            background-color: #efefef;
        }
    </style>
        ';

        echo '<div class="bulk_table">';
        echo '<div class="wdp_pricing_table_caption" style="color: #1e73be; text-align: center; font-weight: bold; margin-bottom: 10px;">En base a la cantidad obtienes mejor precio.</div>';
        echo '<table class="wdp_pricing_table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Piezas</th>';
        echo '<th>Precio Pieza</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($custom_price_table as $row) {
            $from_quantity = $row['from_quantity'];
            $to_quantity = $row['to_quantity'];

            if ($to_quantity === '') {
                $to_quantity = '+';
            }

            $range = $from_quantity !== $to_quantity ? $from_quantity . ' - ' . $to_quantity : $from_quantity;

            // Replace "10 - +" with "10+"
            $range = str_replace('- +', '+', $range);

            $price = number_format($row['price'], 2); // Format with cents

            echo '<tr>';
            echo '<td>' . $range . '</td>';
            echo '<td><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">$</span>' . $price . '</bdi></span></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '<span class="wdp_pricing_table_footer"></span>';
        echo '</div>';
    }
}
add_action('woocommerce_after_add_to_cart_button', 'display_quantity_pricing_table');



// Enqueue the admin scripts
// This function enqueues the necessary scripts for the admin area
function enqueue_admin_scripts($hook)
{
    if ('post.php' !== $hook && 'post-new.php' !== $hook) {
        return;
    }

    wp_enqueue_script('admin-scripts', plugin_dir_url(__FILE__) . 'admin-scripts.js', array('jquery'), '1.0', true);
}
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');

// Enqueue the front-end styles
// This function enqueues the necessary styles for the front-end
function enqueue_frontend_styles()
{
    wp_enqueue_style('frontend-styles', plugin_dir_url(__FILE__) . 'frontend-styles.css');
}
add_action('wp_enqueue_scripts', 'enqueue_frontend_styles');


// Adjust the price HTML to show the range of prices from the price table
// This function adjusts the price HTML to show the range of prices from the price table
function adjust_price_html($price, $product)
{
    $custom_price_table = get_post_meta($product->get_id(), 'custom_price_table', true);

    if (!empty($custom_price_table)) {
        // Sort the array by the price field
        usort($custom_price_table, function ($a, $b) {
            return $a['price'] - $b['price'];
        });

        // Get the highest price in the table as regular price
        $regular_price = end($custom_price_table)['price'];

        // Get the lowest price in the table
        $lowest_price = $custom_price_table[0]['price'];

        // Reformat the price string
        if (is_product()) {
            // On the product page, show "Regular Price - From Lowest Price"
            $price = sprintf('Desde %2$s - %1$s', wc_price($regular_price), wc_price($lowest_price));
        } else {
            // On all other pages, just show "From Lowest Price"
            $price = sprintf('Desde %1$s', wc_price($lowest_price));
        }
    }

    return $price;
}
add_filter('woocommerce_get_price_html', 'adjust_price_html', 10, 2);

// Modify the display of the subtotal in the cart
// This function modifies the display of the subtotal in the cart
function custom_cart_totals_subtotal($subtotal)
{
    global $woocommerce;
    $cart = $woocommerce->cart;

    // Calculate the regular cart subtotal
    $total_regular_price = array_sum(
        array_map(
            function ($cart_item) {
                return $cart_item['data']->get_regular_price() * $cart_item['quantity'];
            },
            $cart->get_cart()
        )
    );

    // Calculate the adjusted cart subtotal
    $total_best_price = array_sum(
        array_map(
            function ($cart_item) {
                return $cart_item['data']->get_price() * $cart_item['quantity'];
            },
            $cart->get_cart()
        )
    );

    if ($total_best_price < $total_regular_price) {
        $subtotal = '<del>' . wc_price($total_regular_price) . '</del> <br /> ' . '<ins>' . wc_price($total_best_price) . '</ins>';
    }

    return $subtotal;
}
add_filter('woocommerce_cart_subtotal', 'custom_cart_totals_subtotal', 10, 1);


// Adjust the price in the mini cart based on the quantity and the price table
// This function adjusts the price in the mini cart based on the quantity and the price table
function adjust_minicart_price($price, $cart_item, $cart_item_key)
{
    $product = $cart_item['data'];
    $custom_price_table = get_post_meta($product->get_id(), 'custom_price_table', true);
    $total_quantity = $cart_item['quantity'];

    if (!empty($custom_price_table)) {
        $best_price = null;

        foreach ($custom_price_table as $row) {
            if ($total_quantity >= $row['from_quantity'] && ($row['to_quantity'] === '' || $total_quantity <= $row['to_quantity'])) {
                if ($best_price === null || $row['price'] < $best_price) {
                    $best_price = $row['price'];
                }
            }
        }

        if ($best_price !== null) {
            $price = wc_price($best_price);
        }
    }

    return $price;
}
add_filter('woocommerce_cart_item_price', 'adjust_minicart_price', 10, 3);

// Adjust the cart hash based on the quantity and the price table
// This function adjusts the cart hash based on the quantity and the price table
function my_custom_cart_hash($hash)
{
    if (!did_action('woocommerce_before_calculate_totals')) {
        adjust_price_based_on_quantity(WC()->cart);
    }
    return hash('md5', wp_json_encode(wc()->cart->get_cart()));
}
add_filter('woocommerce_cart_hash', 'my_custom_cart_hash', 10, 1);



// Adjust the cart fragments based on the quantity and the price table
// This function adjusts the cart fragments based on the quantity and the price table
function my_custom_cart_fragments($fragments)
{
    ob_start();
    woocommerce_cart_totals();
    $cart_totals = ob_get_clean();

    $fragments['.woocommerce-cart-form__totals'] = $cart_totals;

    return $fragments;
}
add_filter('woocommerce_update_order_review_fragments', 'my_custom_cart_fragments', 10, 1);
