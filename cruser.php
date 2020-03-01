<?php

/*
 * Plugin Name: Cruser
 * Description: Add meta fields to users
 * Version: 1.1.1
 * Author: Sergey Levchuk
 * License: GPLv2
 */

/*
 * Добавляем вывод полей с метаданными в меню добавления и редактирования пользователей, используем закрытый ключ RSA для
 * вывода зашифрованных данных
 */

add_action( 'user_new_form', 'extra_user_profile_fields');
add_action( 'show_user_profile', 'extra_user_profile_fields' );
add_action( 'edit_user_profile', 'extra_user_profile_fields' );

function extra_user_profile_fields( $user ) {
	$key = <<<SOMEDATA777
-----BEGIN RSA PRIVATE KEY-----
MIIBPQIBAAJBALqbHeRLCyOdykC5SDLqI49ArYGYG1mqaH9/GnWjGavZM02fos4l
c2w6tCchcUBNtJvGqKwhC5JEnx3RYoSX2ucCAwEAAQJBAKn6O+tFFDt4MtBsNcDz
GDsYDjQbCubNW+yvKbn4PJ0UZoEebwmvH1ouKaUuacJcsiQkKzTHleu4krYGUGO1
mEECIQD0dUhj71vb1rN1pmTOhQOGB9GN1mygcxaIFOWW8znLRwIhAMNqlfLijUs6
rY+h1pJa/3Fh1HTSOCCCCWA0NRFnMANhAiEAwddKGqxPO6goz26s2rHQlHQYr47K
vgPkZu2jDCo7trsCIQC/PSfRsnSkEqCX18GtKPCjfSH10WSsK5YRWAY3KcyLAQIh
AL70wdUu5jMm2ex5cZGkZLRB50yE6rBiHCd5W1WdTFoe
-----END RSA PRIVATE KEY-----
SOMEDATA777;
    $pk  = openssl_get_privatekey($key);
    openssl_private_decrypt(base64_decode(esc_attr( get_the_author_meta( 'address', $user->ID ) )), $address, $pk);
    openssl_private_decrypt(base64_decode(esc_attr( get_the_author_meta( 'phone', $user->ID ) )), $phone, $pk);
    openssl_private_decrypt(base64_decode(esc_attr( get_the_author_meta( 'gender', $user->ID ) )), $gender, $pk);
    openssl_private_decrypt(base64_decode(esc_attr( get_the_author_meta( 'marital', $user->ID ) )), $marital, $pk);
    ?>
    <h3><?php _e("Extra profile information"); ?></h3>

    <table class="form-table">
        <tr>
            <th><label for="address"><?php _e("Address"); ?></label></th>
            <td>
                <input type="text" name="address" id="address" value="<?php echo $address; ?>" class="regular-text" /><br />
                <span class="description"><?php _e("Please enter your address"); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="city"><?php _e("Phone"); ?></label></th>
            <td>
                <input type="text" name="phone" id="phone" value="<?php echo $phone; ?>" class="regular-text" /><br />
                <span class="description"><?php _e("Please enter your phone"); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="postalcode"><?php _e("Gender"); ?></label></th>
            <td>
				<select id="gender" size="1" name="gender">
					<option <?php if ($gender == "woman") echo 'selected="selected"' ?> value="woman"><?php _e("woman"); ?></option>
					<option <?php if ($gender == "man") echo 'selected="selected"' ?> value="man"><?php _e("man"); ?></option>
				</select>
                <br />
                <span class="description"><?php _e("Please select your gender"); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="postalcode"><?php _e("Marital status"); ?></label></th>
            <td>
                <input type="text" name="marital" id="marital" value="<?php echo $marital; ?>" class="regular-text" /><br />
                <span class="description"><?php _e("Please enter your marital status"); ?></span>
            </td>
        </tr>
    </table>
<?php }

/*
 *  добавляем мета данные полей пользователя в БД с применением шифрования с помощью открытого ключа RSA
 */

add_action( 'user_register', 'save_extra_user_profile_fields' );
add_action( 'personal_options_update', 'save_extra_user_profile_fields' );
add_action( 'edit_user_profile_update', 'save_extra_user_profile_fields' );

function save_extra_user_profile_fields( $user_id ) {
    if ( !current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }
    $pub = <<<SOMEDATA777
-----BEGIN PUBLIC KEY-----
MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBALqbHeRLCyOdykC5SDLqI49ArYGYG1mq
aH9/GnWjGavZM02fos4lc2w6tCchcUBNtJvGqKwhC5JEnx3RYoSX2ucCAwEAAQ==
-----END PUBLIC KEY-----
SOMEDATA777;
    $pk  = openssl_get_publickey($pub);
    $data = esc_sql($_POST['address']);
    openssl_public_encrypt($data, $encrypted, $pk);
    update_user_meta( $user_id, 'address', chunk_split(base64_encode($encrypted)) );
    $data = esc_sql($_POST['phone']);
    openssl_public_encrypt($data, $encrypted, $pk);
    update_user_meta( $user_id, 'phone', chunk_split(base64_encode($encrypted)) );
    $data = esc_sql($_POST['gender']);
    openssl_public_encrypt($data, $encrypted, $pk);
    update_user_meta( $user_id, 'gender', chunk_split(base64_encode($encrypted)) );
    $data = esc_sql($_POST['marital']);
    openssl_public_encrypt($data, $encrypted, $pk);
    update_user_meta( $user_id, 'marital', chunk_split(base64_encode($encrypted)) );
}

/*
 *  Создаем шорткод, который выведет список пользователей на страницу и в случае,
 *  если пользователь будет выбран выводит страницу пользователя с его расшифрованными метаполями
 */

add_shortcode( 'cr_users', 'true_cr_func' );

function true_cr_func( $atts ){
    $pageurl = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
    if (substr($pageurl,0,5) == "login") {
        $login = substr($pageurl,6);
        $args = ['blog_id' => $GLOBALS['blog_id'], 'role' => '', 'role__in' => [], 'role__not_in' => [], 'meta_key' => '', 'meta_value' => '', 'meta_compare' => '', 'meta_query' => [], 'date_query' => [], 'include' => [], 'exclude' => [], 'orderby' => 'login', 'order' => 'ASC', 'offset' => '', 'search' => '', 'number' => '', 'count_total' => false, 'fields' => 'all', 'who' => '', 'login' => esc_sql($login)];
        $userone_query = new WP_User_Query( $args );
        if ( ! empty( $userone_query->get_results() ) ) {
            $key = <<<SOMEDATA777
-----BEGIN RSA PRIVATE KEY-----
MIIBPQIBAAJBALqbHeRLCyOdykC5SDLqI49ArYGYG1mqaH9/GnWjGavZM02fos4l
c2w6tCchcUBNtJvGqKwhC5JEnx3RYoSX2ucCAwEAAQJBAKn6O+tFFDt4MtBsNcDz
GDsYDjQbCubNW+yvKbn4PJ0UZoEebwmvH1ouKaUuacJcsiQkKzTHleu4krYGUGO1
mEECIQD0dUhj71vb1rN1pmTOhQOGB9GN1mygcxaIFOWW8znLRwIhAMNqlfLijUs6
rY+h1pJa/3Fh1HTSOCCCCWA0NRFnMANhAiEAwddKGqxPO6goz26s2rHQlHQYr47K
vgPkZu2jDCo7trsCIQC/PSfRsnSkEqCX18GtKPCjfSH10WSsK5YRWAY3KcyLAQIh
AL70wdUu5jMm2ex5cZGkZLRB50yE6rBiHCd5W1WdTFoe
-----END RSA PRIVATE KEY-----
SOMEDATA777;
            $pk  = openssl_get_privatekey($key);
            ob_start();
            ?>
            <div>
                <b><?php _e('USER')?></b>
            </div>
            <br>
            <ul>
                <?php
                foreach($userone_query->get_results() as $user) {
                    ?>
                    <li>
                        <?php
                        esc_html_e('login: ');
                        esc_html_e($user->user_login);
                        echo '<br><br>';
                        $all_meta_for_user = get_user_meta( $user->ID );
                        openssl_private_decrypt(base64_decode(esc_attr($all_meta_for_user['address'][0])), $address, $pk);
                        openssl_private_decrypt(base64_decode(esc_attr($all_meta_for_user['phone'][0])), $phone, $pk);
                        openssl_private_decrypt(base64_decode(esc_attr($all_meta_for_user['gender'][0])), $gender, $pk);
                        openssl_private_decrypt(base64_decode(esc_attr($all_meta_for_user['marital'][0])), $marital, $pk);
                        esc_html_e('address: ');
                        echo $address;
                        echo '<br><br>';
                        esc_html_e('phone: ');
                        echo $phone;
                        echo '<br><br>';
                        esc_html_e('gender: ');
                        echo $gender;
                        echo '<br><br>';
                        esc_html_e('marital: ');
                        echo $marital;
                        ?>
                    </li>
                    <?php
                }
                ?>
            </ul>
            <?php $crusers = ob_get_clean();

            return $crusers;
        }
    }
    $args = ['blog_id' => $GLOBALS['blog_id'], 'role' => '', 'role__in' => [], 'role__not_in' => [], 'meta_key' => '', 'meta_value' => '', 'meta_compare' => '', 'meta_query' => [], 'date_query' => [], 'include' => [], 'exclude' => [], 'orderby' => 'login', 'order' => 'ASC', 'offset' => '', 'search' => '', 'number' => '', 'count_total' => false, 'fields' => 'all', 'who' => '',];
    // The Query
    $user_query = new WP_User_Query( $args );
    $url = $_SERVER['REQUEST_URI'];
    // User Loop
    if ( ! empty( $user_query->get_results() ) ) {
        ob_start();
        ?>
		<div>
            <?php _e('ALL USERS:'); ?>
        </div>
        <br>
        <ul>
            <?php
            foreach ( $user_query->get_results() as $user ) {
                ?>
                <li>
                    <a href="<?php echo add_query_arg( ['login'=>esc_attr($user->user_login)], $url );?>"><?php echo esc_html($user->user_login); ?></a>
                </li>
                <?php
            }
            ?>
        </ul>
        <br>
        <br>
        <?php $crusers = ob_get_clean();
        return $crusers;
    } else {
        return 'No users found';
    }
}

