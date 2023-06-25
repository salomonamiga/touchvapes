<?php
/*
Plugin Name: WooCommerce Quantity Pricing
Plugin URI: http://yourwebsite.com/
Description: Adds quantity-based pricing to WooCommerce products.
Version: 1.0
Author: Your Name
Author URI: http://yourwebsite.com/
*/

// Añadir la pestaña de precios por cantidad
function custom_product_tabs($tabs) {
    $tabs['quantity_pricing'] = array(
        'label' => __('Precios por cantidad', 'woocommerce'),
        'target' => 'quantity_pricing_data',
        'class' => array('show_if_simple', 'show_if_variable'),
    );

    return $tabs;
}
add_filter('woocommerce_product_data_tabs', 'custom_product_tabs');

// Contenido de la pestaña de precios por cantidad
function custom_product_panels() {
    global $post;

    $custom_price_table = get_post_meta($post->ID, 'custom_price_table', true);

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
        // Mostrar una fila vacía por defecto
        echo '<tr>';
        echo '<td><input type="text" name="custom_price_table[0][from_quantity]"></td>';
        echo '<td><input type="text" name="custom_price_table[0][to_quantity]"></td>';
        echo '<td><input type="text" name="custom_price_table[0][price]"></td>';
        echo '<td><button type="button" class="remove_row_button button">Eliminar</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<button type="button" class="add_row_button button">Agregar Fila</button>';
    echo '</div>';
}

add_action('woocommerce_product_data_panels', 'custom_product_panels');

// Guardar los valores del campo de tabla de precios cuando se guarda el producto
function save_custom_price_field($post_id) {
    if (isset($_POST['custom_price_table'])) {
        $custom_price_table = $_POST['custom_price_table'];
        $new_custom_price_table = array();

        foreach ($custom_price_table as $row) {
            $from_quantity = sanitize_text_field($row['from_quantity']);
            $to_quantity = sanitize_text_field($row['to_quantity']);
            $price = sanitize_text_field($row['price']);

            if (!empty($from_quantity) || !empty($to_quantity) || !empty($price)) {
                $new_custom_price_table[] = array(
                    'from_quantity' => $from_quantity,
                    'to_quantity' => $to_quantity,
                    'price' => $price,
                );
            }
        }

        update_post_meta($post_id, 'custom_price_table', $new_custom_price_table);
    } else {
        delete_post_meta($post_id, 'custom_price_table');
    }
}
add_action('woocommerce_process_product_meta', 'save_custom_price_field');


// Ajustar el precio en el carrito de acuerdo con la cantidad total de producto y la tabla de precios
function adjust_price_based_on_quantity($cart) {
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
                $cart_item['data']->set_price($best_price);
            }
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'adjust_price_based_on_quantity');

// Mostrar tabla de precios por cantidad en el front-end
function display_quantity_pricing_table() {
    global $product;

    $custom_price_table = get_post_meta($product->get_id(), 'custom_price_table', true);

    if (!empty($custom_price_table)) {
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

            // Reemplazar "10 - +" con "10+"
            $range = str_replace('- +', '+', $range);

            $price = number_format($row['price'], 2); // Formato con centavos

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


// Mostrar la tabla de precios después del botón "Añadir al carrito"
function display_quantity_pricing_table_after_add_to_cart() {
    global $product;

    echo '<div class="woocommerce-quantity-pricing">';
    echo '<div class="wdp_pricing_table_container">';

    // Mostrar el botón "Añadir al carrito"
    do_action('woocommerce_after_add_to_cart_button');

    // Mostrar la tabla de precios por cantidad
    display_quantity_pricing_table();

    echo '</div>';
    echo '</div>';
}
add_action('woocommerce_after_add_to_cart_form', 'display_quantity_pricing_table_after_add_to_cart');


// Scripts adicionales para la funcionalidad de agregar/eliminar filas
function quantity_pricing_admin_scripts() {
    echo '<script>
        jQuery(document).ready(function($) {
            // Agregar una nueva fila en la tabla de precios por cantidad
            $(document).on("click", ".add_row_button", function() {
                var rowHtml, 
                    rowId = Date.now();

                rowHtml = \'<tr>\' +
                    \'<td><input type="text" name="custom_price_table[\' + rowId + \'][from_quantity]"></td>\' +
                    \'<td><input type="text" name="custom_price_table[\' + rowId + \'][to_quantity]"></td>\' +
                    \'<td><input type="text" name="custom_price_table[\' + rowId + \'][price]"></td>\' +
                    \'<td><button type="button" class="remove_row_button button">Eliminar</button></td>\' +
                    \'</tr>\';
                
                $("#quantity_pricing_table tbody").append(rowHtml);
            });

            // Eliminar una fila de la tabla de precios por cantidad
            $(document).on("click", ".remove_row_button", function() {
                $(this).closest("tr").remove();
            });
        });
    </script>';
}
add_action('admin_footer', 'quantity_pricing_admin_scripts');


// Estilos adicionales para la tabla de precios
function quantity_pricing_styles() {
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
    </style>';
}
add_action('wp_head', 'quantity_pricing_styles');


function adjust_price_html($price, $product) {
    $custom_price_table = get_post_meta($product->get_id(), 'custom_price_table', true);

    if (!empty($custom_price_table)) {
        // ordenar el array por el campo de precio
        usort($custom_price_table, function($a, $b) {
            return $a['price'] - $b['price'];
        });

        // obtener el precio más alto en la tabla como precio regular
        $regular_price = end($custom_price_table)['price'];

        // obtener el precio más bajo en la tabla
        $lowest_price = $custom_price_table[0]['price'];

        // reformatear la cadena de precios
        if (is_product()) {
            // en la página del producto, mostrar "Precio Regular - Desde Precio Más Bajo"
            $price = sprintf('%1$s - Desde %2$s', wc_price($regular_price), wc_price($lowest_price));
        } else {
            // en todas las demás páginas, sólo mostrar "Desde Precio Más Bajo"
            $price = sprintf('Desde %1$s', wc_price($lowest_price));
        }
    }

    return $price;
}
add_filter('woocommerce_get_price_html', 'adjust_price_html', 10, 2);
