<?php
      error_reporting(E_ALL);
      ini_set('display_errors', 0);
      ini_set('log_errors', 1);

      require_once __DIR__ . '/admin/lib/contact-intake.php';

      if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $intake = mcg_contact_intake();
        $to = mcg_setting('admin_email', 'marksdanyal7@gmail.com');
        if (!mcg_valid_email($to)) {
            $to = 'marksdanyal7@gmail.com';
        }
        $name = htmlspecialchars($intake['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $from = htmlspecialchars($intake['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = "Contact Form Message From The Website";
        $messageFromForm = $intake['message'];
        $messageFromFormIf = nl2br(htmlspecialchars($intake['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);

        if (!empty($intake['spam'])) {
            echo "Thank you for your message";
            exit;
        }

        // $subject = "Stuff";
        // $from = "marksdanyal7@gmail.com";
        // $body = "stuff2 and words and things";
  
        $message = "
        <!DOCTYPE html>
        <html>
          <head>
            <meta name='viewport' content='width=device-width, initial-scale=1.0' />
            <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
            <title>MCG Biokineticist</title>
            <style>
              /* -------------------------------------
                  GLOBAL RESETS
              ------------------------------------- */
        
              /*All the styling goes here*/
        
              img {
                border: none;
                -ms-interpolation-mode: bicubic;
                max-width: 100%;
              }
        
              body {
                background-color: #f6f6f6;
                font-family: sans-serif;
                -webkit-font-smoothing: antialiased;
                font-size: 14px;
                line-height: 1.4;
                margin: 0;
                padding: 0;
                -ms-text-size-adjust: 100%;
                -webkit-text-size-adjust: 100%;
              }
        
              table {
                border-collapse: separate;
                mso-table-lspace: 0pt;
                mso-table-rspace: 0pt;
                width: 100%;
              }
        
              table td {
                font-family: sans-serif;
                font-size: 14px;
                vertical-align: top;
              }
        
              /* -------------------------------------
                  BODY & CONTAINER
              ------------------------------------- */
        
              .body {
                background-color: #f6f6f6;
                width: 100%;
              }
        
              /* Set a max-width, and make it display as block so it will automatically stretch to that width, but will also shrink down on a phone or something */
              .container {
                display: block;
                margin: 0 auto !important;
                /* makes it centered */
                max-width: 580px;
                padding: 10px;
                width: 580px;
              }
        
              /* This should also be a block element, so that it will fill 100% of the .container */
              .content {
                box-sizing: border-box;
                display: block;
                margin: 0 auto;
                max-width: 580px;
                padding: 10px;
              }
        
              /* -------------------------------------
                  HEADER, FOOTER, MAIN
              ------------------------------------- */
              .main {
                color: #ffffff;
                background: #97b9bb;
                border-radius: 0px;
                width: 100%;
              }
        
              .midText {
                color: rgb(65, 64, 64) !important;
                background: #c5d8d9 !important;
                border-radius: 0px;
                width: 100%;
              }
        
              .lowText {
                color: #000000 !important;
                background: #e4eded !important;
                border-radius: 0px;
                width: 100%;
              }
        
              .wrapper {
                box-sizing: border-box;
                padding: 20px;
              }
        
              .content-block {
                padding-bottom: 10px;
                padding-top: 10px;
              }
        
              .footer {
                clear: both;
                margin-top: 10px;
                text-align: center;
                width: 100%;
              }
        
              .footer td,
              .footer p,
              .footer span,
              .footer a {
                color: #999999;
                font-size: 12px;
                text-align: center;
              }
        
              /* -------------------------------------
                  TYPOGRAPHY
              ------------------------------------- */
              h1,
              h2,
              h3,
              h4 {
                color: #ffffff;
                font-family: sans-serif;
                font-weight: 400;
                line-height: 1.4;
                margin: 0;
                margin-bottom: 30px;
              }
        
              h1 {
                font-size: 35px;
                font-weight: 300;
                text-align: center;
                text-transform: capitalize;
              }
        
              p,
              ul,
              ol {
                font-family: sans-serif;
                font-size: 16px;
                font-weight: normal;
                margin: 0;
                margin-bottom: 15px;
              }
        
              p li,
              ul li,
              ol li {
                list-style-position: inside;
                margin-left: 5px;
              }
        
              a {
                color: #115e62;
                text-decoration: underline;
              }
        
              /* -------------------------------------
                  BUTTONS
              ------------------------------------- */
              .btn {
                box-sizing: border-box;
                width: 100%;
              }
        
              .btn > tbody > tr > td {
                padding-bottom: 15px;
              }
        
              .btn table {
                width: auto;
              }
        
              .btn table td {
                background-color: #ffffff;
                border-radius: 5px;
                text-align: center;
              }
        
              .btn a {
                background-color: #ffffff;
                border: solid 1px #115e62;
                border-radius: 5px;
                box-sizing: border-box;
                color: #115e62;
                cursor: pointer;
                display: inline-block;
                font-size: 14px;
                font-weight: bold;
                margin: 0;
                padding: 12px 25px;
                text-decoration: none;
                text-transform: capitalize;
              }
        
              .btn-primary table td {
                background-color: #115e62;
              }
        
              .btn-primary a {
                background-color: #115e62;
                border-color: #115e62;
                color: #ffffff;
              }
        
              /* -------------------------------------
                  OTHER STYLES THAT MIGHT BE USEFUL
              ------------------------------------- */
              .last {
                margin-bottom: 0;
              }
        
              .first {
                margin-top: 0;
              }
        
              .align-center {
                text-align: center;
              }
        
              .align-right {
                text-align: right;
              }
        
              .align-left {
                text-align: left;
              }
        
              .clear {
                clear: both;
              }
        
              .mt0 {
                margin-top: 0;
              }
        
              .mb0 {
                margin-bottom: 0;
              }
        
              .preheader {
                color: transparent;
                display: none;
                height: 0;
                max-height: 0;
                max-width: 0;
                opacity: 0;
                overflow: hidden;
                mso-hide: all;
                visibility: hidden;
                width: 0;
              }
        
              .powered-by a {
                text-decoration: none;
              }
        
              hr {
                border: 0;
                border-bottom: 1px solid #f6f6f6;
                margin: 20px 0;
              }
        
              /* -------------------------------------
                  RESPONSIVE AND MOBILE FRIENDLY STYLES
              ------------------------------------- */
              @media only screen and (max-width: 620px) {
                table.body h1 {
                  font-size: 28px !important;
                  margin-bottom: 10px !important;
                }
        
                table.body p,
                table.body ul,
                table.body ol,
                table.body td,
                table.body span,
                table.body a {
                  font-size: 16px !important;
                }
        
                table.body .wrapper,
                table.body .article {
                  padding: 10px !important;
                }
        
                table.body .content {
                  padding: 0 !important;
                }
        
                table.body .container {
                  padding: 0 !important;
                  width: 100% !important;
                }
        
                table.body .main {
                  border-left-width: 0 !important;
                  border-radius: 0 !important;
                  border-right-width: 0 !important;
                }
        
                table.body .btn table {
                  width: 100% !important;
                }
        
                table.body .btn a {
                  width: 100% !important;
                }
        
                table.body .img-responsive {
                  height: auto !important;
                  max-width: 100% !important;
                  width: auto !important;
                }
              }
        
              /* -------------------------------------
                  PRESERVE THESE STYLES IN THE HEAD
              ------------------------------------- */
              @media all {
                .ExternalClass {
                  width: 100%;
                }
        
                .ExternalClass,
                .ExternalClass p,
                .ExternalClass span,
                .ExternalClass font,
                .ExternalClass td,
                .ExternalClass div {
                  line-height: 100%;
                }
        
                .apple-link a {
                  color: inherit !important;
                  font-family: inherit !important;
                  font-size: inherit !important;
                  font-weight: inherit !important;
                  line-height: inherit !important;
                  text-decoration: none !important;
                }
        
                #MessageViewBody a {
                  color: inherit;
                  text-decoration: none;
                  font-size: inherit;
                  font-family: inherit;
                  font-weight: inherit;
                  line-height: inherit;
                }
        
                .btn-primary table td:hover {
                  background-color: #34495e !important;
                }
        
                .btn-primary a:hover {
                  background-color: #34495e !important;
                  border-color: #34495e !important;
                }
              }
            </style>
          </head>
        
          <body>
            <span class='preheader'
              >Contact form message from the website</span
            >
            <table
              role='presentation'
              border='0'
              cellpadding='0'
              cellspacing='0'
              class='body'
            >
              <tr>
                <td>&nbsp;</td>
                <td class='container'>
                  <div class='content'>
                    <!-- START CENTERED WHITE CONTAINER -->
                    <table role='presentation' class='main'>
                      <!-- START MAIN CONTENT AREA -->
                      <tr>
                        <td class='wrapper'>
                          <table
                            role='presentation'
                            border='0'
                            cellpadding='0'
                            cellspacing='0'
                          >
                            <tr>
                              <td>
                                <h1>
                                  <img
                                    class='me-3 img-fluid'
                                    src='http://www.dmarksproject.co.za/test/website/img/MCG%20BIOKINETICS%20LOGO2.png'
                                    alt='Icon'
                                  />
                                  MCG Biokinetics
                                </h1>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <!-- END MAIN CONTENT AREA -->
                    </table>
                    <!-- END CENTERED WHITE CONTAINER -->
                    <!-- START CENTERED WHITE CONTAINER -->
                    <table role='presentation' class='midText'>
                      <!-- START MAIN CONTENT AREA -->
                      <tr>
                        <td class='wrapper'>
                          <table
                            role='presentation'
                            border='0'
                            cellpadding='0'
                            cellspacing='0'
                          >
                            <tr>
                              <td>
                                <h3 style='color: #665785;'>Hi there,</h3>
                                <p>
                                  This is a contact form message from the website
                                </p>                                
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <!-- END MAIN CONTENT AREA -->
                    </table>
                    <!-- END CENTERED WHITE CONTAINER -->
                    <!-- START CENTERED WHITE CONTAINER -->
                    <table role='presentation' class='lowText'>
                      <!-- START MAIN CONTENT AREA -->
                      <tr>
                        <td class='wrapper'>
                          <table
                            role='presentation'
                            border='0'
                            cellpadding='0'
                            cellspacing='0'
                          >
                            <tr>
                              <td>
                                <p>
                                  Name: ".$name."
                                </p>
                                <p>
                                  Email: ".$from."
                                </p>
                                <h2 style='color: #115e62;'>".$messageFromFormIf." </h2>
                                <h3 style='color: #665785;'>When applicable, please respond to their message directly to their email address</h3>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <!-- END MAIN CONTENT AREA -->
                    </table>
                    <!-- END CENTERED WHITE CONTAINER -->
                    <!-- START FOOTER -->
                    <div class='footer'>
                      <table
                        role='presentation'
                        border='0'
                        cellpadding='0'
                        cellspacing='0'
                      >
                        <tr>
                          <td class='content-block'>
                            <span class='apple-link'
                              >23 4th Avenue, Parktown North, Johannesburg, 2193, South
                              Africa</span
                            >
                            <!-- <br> Don't like these emails? <a href='http://i.imgur.com/CScmqnj.gif'>Unsubscribe</a>. -->
                          </td>
                        </tr>
                        <tr>
                          <td class='content-block powered-by'>
                            <!-- Powered by <a href='http://htmlemail.io'>HTMLemail</a>. -->
                            © MCG Biokinetics, All Right Reserved
                          </td>
                        </tr>
                      </table>
                    </div>
                    <!-- END FOOTER -->
                  </div>
                </td>
                <td>&nbsp;</td>
              </tr>
            </table>
          </body>
        </html>
        ";
  
        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
  
        // More headers
        $headers .= 'From: <danyalmarks@dmarksdev.co.za>' . "\r\n";
        $headers .= 'Reply-To: ' . $intake['email'] . "\r\n";

        if (mail($to,$subject,$message,$headers)) {
              mcg_update_message((int) $intake['id'], ['email_sent' => true]);
              echo "Thank you for your message";
        } else {
              echo "We could not send your message. Please try again or call the practice.";
        }
      }


?>