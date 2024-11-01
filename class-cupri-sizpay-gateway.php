<?php
/*
Plugin Name: پرداخت دلخواه برای سیزپی
Plugin URI: https://www.sizpay.ir
Author: سیزپی
Author URI: https://www.sizpay.ir
Version: 1.3.3
Description: با این پلاگین میتونید سیستم پرداخت خودتون رو راه اندازی کنید.
 */

define( 'wppdsp_sizpay_plugin_dir', plugin_dir_path( __FILE__ ) );
add_action( 'plugins_loaded', 'wppdsp_padrakht_delkhah_sizpay' );
function wppdsp_padrakht_delkhah_sizpay() {
	if ( ! class_exists( 'cupri_abstract_gateway' ) ) {
		function wppdsp_sizpay_no_parent_plugin_exists() {
			?>
            <div class="error notice">
                <p>پرداخت دلخواه سیزپی : پلاگین پرداخت دلخواه نصب و یا فعال نیست. <a
                            href="<?php echo admin_url( 'plugin-install.php?tab=plugin-information&plugin=pardakht-delkhah&TB_iframe=true&width=600&height=550' ); ?>">نصب</a>
                </p>
            </div>
			<?php
		}


		add_action( 'admin_notices', 'wppdsp_sizpay_no_parent_plugin_exists' );

		return;
	}


	class wppdsp_cupri_sizpay_gateway extends cupri_abstract_gateway {
		static protected $instance = null;

		function add_settings( $settings ) {
			$settings['MerchantId'] = 'کد پذیرنده';
			$settings['TerminalId'] = 'کد ترمینال';
			$settings['key1']       = 'کلید۱/ نام کاربری';
			$settings['key2']       = 'کلید۲/ رمز عبور';

			return $settings;
		}

		function start( $payment_data ) {
			$order_id      = $payment_data['order_id'];
			$price         = $payment_data['price'];
			$callback_url  = add_query_arg( array( 'order_id' => $order_id ), $this->callback_url );
			$MerchantId    = trim( $this->settings['MerchantId'] );
			$TerminalId    = trim( $this->settings['TerminalId'] );
			$key1_username = trim( $this->settings['key1'] );
			$key2_password = trim( $this->settings['key2'] );

			$amount      = $price * 10; //Rial
			$invoiceDate = date( "Y/m/d H:i:s" );

			// get token
			$token_url    = 'https://rt.sizpay.ir/api/PaymentSimple/GetTokenSimple';
			$token_params = [
				'UserName'    => $key1_username,
				'Password'    => $key2_password,
				'MerchantID'  => $MerchantId,
				'TerminalID'  => $TerminalId,
				'Amount'      => $amount,
				'DocDate'     => $invoiceDate,
				'OrderID'     => $order_id,
				'ReturnURL'   => $callback_url,
				'ExtraInf'    => '',
				'InvoiceNo'   => $order_id,
				'AppExtraInf' => json_encode( [
					'PayerNm'     => '',
					'PayerMobile' => sanitize_text_field( ( isset( $_POST['cupri_fmobile'] ) ? $_POST['cupri_fmobile'] : '' ) ),
					'PayerEmail'  => sanitize_email( ( isset( $_POST['cupri_femail'] ) ? $_POST['cupri_femail'] : '' ) ),
					'Descr'       => '',
					'PayerIP'     => '',
					'PayTitle'    => '',
				] ),
				'SignData'    => '',
			];
			$res          = $this->post_data( $token_url, $token_params );

			if ( ! ( isset( $res->ResCod ) && ( $res->ResCod == '0' || $res->ResCod == '00' ) && isset( $res->Token ) && ! empty( $res->Token ) ) ) {
				//fail
				$error_code    = isset( $res->ResCod ) ? $res->ResCod : ' نامشخص ';
				$error_message = isset( $res->Message ) ? $res->Message : ' نامشخص ';
				$msg           = $error_message . ' - ' . $error_code;
				echo cupri_failed_msg( esc_html( $msg ) );

				return;
			}
			$token = $res->Token;

			echo cupri_success_msg( 'در حال انتقال به بانک...' );

			echo '
                <script language="javascript" type="text/javascript">
                    function sizpay_postData() {
                     var form = document.createElement("form");
                     form.setAttribute("method", "POST");
                     form.setAttribute("action", "https://rt.sizpay.ir/Route/Payment");
                     form.setAttribute("target", "_self");
                     var hiddenField3 = document.createElement("input");
                     hiddenField3.setAttribute("name", "MerchantID");
                     hiddenField3.setAttribute("value", "' . $MerchantId . '");
                     form.appendChild(hiddenField3);
                     var hiddenField4 = document.createElement("input");
                     hiddenField4.setAttribute("name", "TerminalID");
                     hiddenField4.setAttribute("value", "' . $TerminalId . '");
                     form.appendChild(hiddenField4);
                     var hiddenField5 = document.createElement("input");
                     hiddenField5.setAttribute("name", "Token");
                     hiddenField5.setAttribute("value", "' . $token . '");
                     form.appendChild(hiddenField5);
                     document.body.appendChild(form);
                     form.submit();
                     document.body.removeChild(form);
                    }
                    sizpay_postData();
                </script>
                
                
                <noscript>
                    <form method="POST" action="https://rt.sizpay.ir/Route/Payment" target="_self">
                        <input type="hidden" name="MerchantID" value="' . $MerchantId . '">
                        <input type="hidden" name="TerminalID" value="' . $TerminalId . '">
                        <input type="hidden" name="Token" value="' . $token . '">
                        <input type="submit" value="برای هدایت به درگاه کلیک کنید">
                    </form>
                </noscript>
                            ';

			return;


		}

		function end( $payment_data ) {
			$OrderId       = $order_id = $res_id = sanitize_text_field( $_REQUEST['order_id'] );
			$Amount        = $this->get_price( $order_id ) * 10;//Rial
			$MerchantId    = trim( $this->settings['MerchantId'] );
			$TerminalId    = trim( $this->settings['TerminalId'] );
			$key1_username = trim( $this->settings['key1'] );
			$key2_password = trim( $this->settings['key2'] );
			$this->msg     = [
				'status'  => false,
				'message' => 'نامشخص',
			];

			/***
			 *  die(var_export($ _ POST,1));
			 * array (
			 * 'ResCod' => '-1',
			 * 'Message' => 'تراکنش ارسالي معتبر نيست',
			 * 'MerchantID' => '1',
			 * 'TerminalID' => '1',
			 * 'OrderID' => '559',
			 * 'ExtraInf' => '',
			 * 'InvoiceNo' => '559',
			 * 'AppExtraInf' => '{\\\'PayerNm\\\':\\\'\\\',\\\'PayerMobile\\\':\\\'\\\',\\\'PayerEmail\\\':\\\'\\\',\\\'PayerIP\\\':\\\'\\\',\\\'Descr\\\':\\\'\\\',\\\'PayTitleID\\\':0}',
			 * 'Token' => '1',
			 * 'RefNo' => '1',
			 * 'Amount' => '1', )
			 */

			if ( isset( $_POST['ResCod'] ) && ( $_POST['ResCod'] == '0' || $_POST['ResCod'] == '00' ) ) {
				$post_Message     = sanitize_text_field( $_POST['Message'] );
				$post_MerchantID  = sanitize_text_field( $_POST['MerchantID'] );
				$post_TreminalID  = sanitize_text_field( $_POST['TreminalID'] );
				$post_InvoiceNo   = sanitize_text_field( $_POST['InvoiceNo'] );
				$post_ExtraInf    = sanitize_text_field( $_POST['ExtraInf'] );
				$post_AppExtraInf = sanitize_text_field( $_POST['AppExtraInf'] );
				$post_Token       = sanitize_text_field( $_POST['Token'] );

				$confirm_url    = 'https://rt.sizpay.ir/api/PaymentSimple/ConfirmSimple';
				$confirm_params = [
					'UserName'   => $key1_username,
					'Password'   => $key2_password,
					'MerchantID' => $MerchantId,
					'TerminalID' => $TerminalId,
					'Token'      => $post_Token,
					'SignData'   => '',
				];
				$res            = $this->post_data( $confirm_url, $confirm_params );

				if ( ! ( isset( $res->ResCod ) && ( $res->ResCod == '0' || $res->ResCod == '00' ) ) ) {
					//fail
					$error_code           = isset( $res->ResCod ) ? $res->ResCod : ' نامشخص ';
					$error_message        = isset( $res->Message ) ? $res->Message : ' نامشخص ';
					$msg                  = $error_message . ' - ' . $error_code;
					$this->msg['status']  = 0;
					$this->msg['message'] = $msg;
				} else {
					//ok
					$this->msg['status']  = 1;
					$this->msg['message'] = 'با تشکر از پرداخت';
				}


			} else {
				$this->msg['status']  = 0;
				$this->msg['message'] = sanitize_text_field( ( isset( $_POST['Message'] ) ? $_POST['Message'] : 'نامشخص' ) );
			}
			#On Success
			if ( $this->msg['status'] == 1 ) {
				$TraceNo              = $res->TraceNo;
				$RefNo                = $res->RefNo;
				$CardNo               = $res->CardNo;
				$this->msg['message'] = 'با تشکر از خرید شما،پرداخت شما انجام گردید <br/> رسید دیجیتالی-سیزپی: ' . $RefNo;
				$this->msg['class']   = 'success';
				//success
				$this->success( $order_id );
				$this->set_res_code( $order_id, $RefNo );
				echo cupri_success_msg( 'پرداخت شما با موفقیت انجام شد.با تشکر. کد رهگیری:' . esc_html( $order_id ), $order_id );

				return;
			}

			#On Error
			if ( $this->msg['status'] == 0 ) {
				echo cupri_failed_msg( esc_html( $this->msg['message'] ) );
				$this->failed( $order_id );

				return;

			}

		}

		public function post_data( $url, $data, $headers = array() ) {

			$headers = array_merge( array( 'content-type:application/json;charset=utf-8' ), $headers );

			$result = wp_remote_post( $url, array(
					'method'  => 'POST',
					'headers' => $headers,
					'body'    => $data,
				)
			);


			if ( is_wp_error( $result ) || ! $result ) {
				return false;
			}

			if ( $result && ! is_array( $result ) ) {
				return (object) [ 'Message' => $result ];
			}

			if ( $result && isset( $result['body'] ) ) {
				return json_decode( $result['body'] );
			}

			return $result;
		}


	}

	wppdsp_cupri_sizpay_gateway::get_instance( 'sizpay', 'سیزپی' );
}
