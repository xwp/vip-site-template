<?php
/**
 * Homepage Pattern.
 *
 * @package Newspack
 */

return array(
	'post_title'   => __( 'Thank you!', 'newspack' ),
	'post_content' => '<!-- wp:heading -->
<h2>Thank You!</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Your contribution really helps!</p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>Amount paid</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>*AMOUNT*</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>Date paid</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>*DATE*</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>Payment method</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>*PAYMENT_METHOD*</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:paragraph -->
<p>If you have any questions, you can reach us at *CONTACT_EMAIL*.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Download the receipt at *RECEIPT_URL*.</p>
<!-- /wp:paragraph -->',
	'email_html'   => '    <!doctype html>
    <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
      <head>
        <title>
          Thank you!
        </title>
        <!--[if !mso]><!-->
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <!--<![endif]-->
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style type="text/css">
          #outlook a { padding:0; }
          body { margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%; }
          table, td { border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt; }
          img { border:0;height:auto;line-height:100%; outline:none;text-decoration:none;-ms-interpolation-mode:bicubic; }
          p { display:block;margin:13px 0; }
        </style>
        <!--[if mso]>
        <xml>
        <o:OfficeDocumentSettings>
          <o:AllowPNG/>
          <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
        </xml>
        <![endif]-->
        <!--[if lte mso 11]>
        <style type="text/css">
          .mj-outlook-group-fix { width:100% !important; }
        </style>
        <![endif]-->


    <style type="text/css">
      @media only screen and (min-width:480px) {
        .mj-column-per-100 { width:100% !important; max-width: 100%; }
.mj-column-per-33-333333333333 { width:33.333333333333% !important; max-width: 33.333333333333%; }
      }
    </style>
    <style media="screen and (min-width:480px)">
      .moz-text-html .mj-column-per-100 { width:100% !important; max-width: 100%; }
.moz-text-html .mj-column-per-33-333333333333 { width:33.333333333333% !important; max-width: 33.333333333333%; }
    </style>

        <style type="text/css">


        </style>
        <style type="text/css">/* Paragraph */
p {
	margin-top: 0 !important;
	margin-bottom: 0 !important;
}

/* Link */
a {
	color: inherit !important;
	text-decoration: underline;
}
a:active, a:focus, a:hover {
	text-decoration: none;
}
a:focus {
	outline: thin dotted #000;
}

/* Button */
.is-style-outline a {
	background: #fff !important;
	border: 2px solid !important;
	display: block !important;
}

/* Heading */
h1 { font-size: 2.44em; line-height: 1.4; }
h2 { font-size: 1.95em; line-height: 1.4; }
h3 { font-size: 1.56em; line-height: 1.4; }
h4 { font-size: 1.25em; line-height: 1.5; }
h5 { font-size: 1em; line-height: 1.8; }
h6 { font-size: 0.8em; line-height: 1.8; }
h1, h2, h3, h4, h5, h6 { margin-top: 0; margin-bottom: 0; }

/* List */
ul, ol {
	margin-bottom: 0;
	margin-top: 0;
	padding-left: 1.3em;
}

/* Quote */
blockquote,
.wp-block-quote {
	border-left: 4px solid #000;
	margin: 0;
	padding-left: 20px;
}
blockquote cite,
.wp-block-quote cite {
	color: #767676;
}
blockquote p,
.wp-block-quote p {
	padding-bottom: 12px;
}
.wp-block-quote.is-style-large {
	border-left: 0;
	padding-left: 0;
}
.wp-block-quote.is-style-large p {
	font-size: 24px;
	font-style: italic;
	line-height: 1.6;
}

/* Image */
@media all and (max-width: 590px) {
	img {
		height: auto !important;
	}
}

/* Social links */
.social-element img {
	border-radius: 0 !important;
}

/* Has Background */
.mj-column-has-width .has-background,
.mj-column-per-50 .has-background {
	padding: 12px;
}

/* Mailchimp Footer */
#canspamBarWrapper {
	border: 0 !important;
}</style>

      </head>
      <body style="word-spacing:normal;background-color:#ffffff;">


      <div
         style="background-color:#ffffff;"
      >


      <!--[if mso | IE]><table align="center" border="0" cellpadding="0" cellspacing="0" class="" style="width:600px;" width="600" ><tr><td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;"><![endif]-->


      <div  style="margin:0px auto;max-width:600px;">

        <table
           align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;"
        >
          <tbody>
            <tr>
              <td
                 style="direction:ltr;font-size:0px;padding:0;text-align:center;"
              >
                <!--[if mso | IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td class="" style="vertical-align:top;width:600px;" ><![endif]-->

      <div
         class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"
      >

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%"
      >
        <tbody>
          <tr>
            <td  style="vertical-align:top;padding:12px;">

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" style="" width="100%"
      >
        <tbody>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Arial;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><h2>Thank You! </h2></div>

                </td>
              </tr>

        </tbody>
      </table>

            </td>
          </tr>
        </tbody>
      </table>

      </div>

          <!--[if mso | IE]></td></tr></table><![endif]-->
              </td>
            </tr>
          </tbody>
        </table>

      </div>


      <!--[if mso | IE]></td></tr></table><table align="center" border="0" cellpadding="0" cellspacing="0" class="" style="width:600px;" width="600" ><tr><td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;"><![endif]-->


      <div  style="margin:0px auto;max-width:600px;">

        <table
           align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;"
        >
          <tbody>
            <tr>
              <td
                 style="direction:ltr;font-size:0px;padding:0;text-align:center;"
              >
                <!--[if mso | IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td class="" style="vertical-align:top;width:600px;" ><![endif]-->

      <div
         class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"
      >

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%"
      >
        <tbody>
          <tr>
            <td  style="vertical-align:top;padding:12px;">

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" style="" width="100%"
      >
        <tbody>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Georgia;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><p>Your contribution really helps!</p></div>

                </td>
              </tr>

        </tbody>
      </table>

            </td>
          </tr>
        </tbody>
      </table>

      </div>

          <!--[if mso | IE]></td></tr></table><![endif]-->
              </td>
            </tr>
          </tbody>
        </table>

      </div>


      <!--[if mso | IE]></td></tr></table><table align="center" border="0" cellpadding="0" cellspacing="0" class="" style="width:600px;" width="600" ><tr><td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;"><![endif]-->


      <div  style="margin:0px auto;max-width:600px;">

        <table
           align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;"
        >
          <tbody>
            <tr>
              <td
                 style="direction:ltr;font-size:0px;padding:0;text-align:center;"
              >
                <!--[if mso | IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td class="mj-column-has-width-outlook" style="vertical-align:top;width:199.999999999998px;" ><![endif]-->

      <div
         class="mj-column-per-33-333333333333 mj-outlook-group-fix mj-column-has-width" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"
      >

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%"
      >
        <tbody>
          <tr>
            <td  style="vertical-align:top;padding:12px;">

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" style="" width="100%"
      >
        <tbody>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Arial;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><h3>Amount paid</h3></div>

                </td>
              </tr>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Georgia;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><p>*AMOUNT*</p></div>

                </td>
              </tr>

        </tbody>
      </table>

            </td>
          </tr>
        </tbody>
      </table>

      </div>

          <!--[if mso | IE]></td><td class="mj-column-has-width-outlook" style="vertical-align:top;width:199.999999999998px;" ><![endif]-->

      <div
         class="mj-column-per-33-333333333333 mj-outlook-group-fix mj-column-has-width" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"
      >

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%"
      >
        <tbody>
          <tr>
            <td  style="vertical-align:top;padding:12px;">

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" style="" width="100%"
      >
        <tbody>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Arial;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><h3>Date paid</h3></div>

                </td>
              </tr>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Georgia;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><p>*DATE*</p></div>

                </td>
              </tr>

        </tbody>
      </table>

            </td>
          </tr>
        </tbody>
      </table>

      </div>

          <!--[if mso | IE]></td><td class="mj-column-has-width-outlook" style="vertical-align:top;width:199.999999999998px;" ><![endif]-->

      <div
         class="mj-column-per-33-333333333333 mj-outlook-group-fix mj-column-has-width" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"
      >

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%"
      >
        <tbody>
          <tr>
            <td  style="vertical-align:top;padding:12px;">

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" style="" width="100%"
      >
        <tbody>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Arial;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><h3>Payment method</h3></div>

                </td>
              </tr>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Georgia;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><p>*PAYMENT_METHOD*</p></div>

                </td>
              </tr>

        </tbody>
      </table>

            </td>
          </tr>
        </tbody>
      </table>

      </div>

          <!--[if mso | IE]></td></tr></table><![endif]-->
              </td>
            </tr>
          </tbody>
        </table>

      </div>


      <!--[if mso | IE]></td></tr></table><table align="center" border="0" cellpadding="0" cellspacing="0" class="" style="width:600px;" width="600" ><tr><td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;"><![endif]-->


      <div  style="margin:0px auto;max-width:600px;">

        <table
           align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;"
        >
          <tbody>
            <tr>
              <td
                 style="direction:ltr;font-size:0px;padding:0;text-align:center;"
              >
                <!--[if mso | IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td class="" style="vertical-align:top;width:600px;" ><![endif]-->

      <div
         class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"
      >

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%"
      >
        <tbody>
          <tr>
            <td  style="vertical-align:top;padding:12px;">

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" style="" width="100%"
      >
        <tbody>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Georgia;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><p>If you have any questions, you can reach us at *CONTACT_EMAIL*.</p></div>

                </td>
              </tr>

        </tbody>
      </table>

            </td>
          </tr>
        </tbody>
      </table>

      </div>

          <!--[if mso | IE]></td></tr></table><![endif]-->
              </td>
            </tr>
          </tbody>
        </table>

      </div>


      <!--[if mso | IE]></td></tr></table><table align="center" border="0" cellpadding="0" cellspacing="0" class="" style="width:600px;" width="600" ><tr><td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;"><![endif]-->


      <div  style="margin:0px auto;max-width:600px;">

        <table
           align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;"
        >
          <tbody>
            <tr>
              <td
                 style="direction:ltr;font-size:0px;padding:0;text-align:center;"
              >
                <!--[if mso | IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td class="" style="vertical-align:top;width:600px;" ><![endif]-->

      <div
         class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"
      >

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%"
      >
        <tbody>
          <tr>
            <td  style="vertical-align:top;padding:12px;">

      <table
         border="0" cellpadding="0" cellspacing="0" role="presentation" style="" width="100%"
      >
        <tbody>

              <tr>
                <td
                   align="left" style="font-size:0px;padding:0;word-break:break-word;"
                >

      <div
         style="font-family:Georgia;font-size:16px;line-height:1.8;text-align:left;color:#000000;"
      ><p>Download the receipt at *RECEIPT_URL*.</p></div>

                </td>
              </tr>

        </tbody>
      </table>

            </td>
          </tr>
        </tbody>
      </table>

      </div>

          <!--[if mso | IE]></td></tr></table><![endif]-->
              </td>
            </tr>
          </tbody>
        </table>

      </div>


      <!--[if mso | IE]></td></tr></table><![endif]-->


      </div>

      </body>
    </html>
  ',
);
