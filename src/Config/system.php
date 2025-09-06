<?php

return [
    [
        'key'    => 'sales.payment_methods.razorpay',       
        'info'   => 'Professional Razorpay Payment Gateway for Bagisto. <div style="margin-top: 10px; padding: 15px; background: #f8f9ff; border: 1px solid #e3f2fd; border-radius: 8px;"><div style="display: flex; align-items: center; margin-bottom: 8px;"><span style="color: #3f84f7; font-weight: 600; font-size: 14px;">🚀 Get Your License Key</span></div><p style="margin: 0 0 10px 0; color: #424242; font-size: 13px; line-height: 1.4;">Unlock advanced Razorpay features, customizable themes, priority support, and regular updates.</p><a href="https://myapps.wontonee.com/" target="_blank" style="display: inline-flex; align-items: center; background: #3f84f7; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; font-size: 13px; transition: all 0.2s;"><i class="fas fa-key" style="margin-right: 6px;"></i>Get License Now<i class="fas fa-external-link-alt" style="margin-left: 6px; font-size: 11px;"></i></a><div style="margin-top: 10px; font-size: 12px; color: #666;"><span style="color: #4caf50;">✓</span> 7-day free trial available</div><div style="margin-top: 8px; padding: 8px; background: #fff3e0; border: 1px solid #ffcc02; border-radius: 4px; font-size: 12px;"><span style="color: #f57c00; font-weight: 600;">💬 Special Discount!</span> <span style="color: #424242;">Contact us on WhatsApp <a href="https://wa.me/919711381236" target="_blank" style="color: #25d366; font-weight: 600; text-decoration: none;">+91 9711381236</a> to avail exclusive discount offers!</span></div><div style="margin-top: 12px; padding: 10px; background: #fff8e1; border: 1px solid #ffc107; border-radius: 6px; font-size: 12px;"><span style="color: #e65100; font-weight: 600;">⚠️ Important Notice:</span> <span style="color: #424242;">Razorpay does not accept decimal amounts. All payment and refund amounts are automatically rounded to the nearest whole number before processing. Orders with rounded amounts will show a note in admin order view.</span></div></div>',
        'name'   => 'Razorpay',
        'sort'   => 5,
        'fields' => [
            [
                'name'          => 'title',
                'title'         => 'RazorPay Payment Gateway',
                'type'          => 'text',
                'validation'    => 'required',
                'channel_based' => true,
                'locale_based'  => true,
            ],
            [
                'name'          => 'description',
                'title'         => '',
                'type'          => 'textarea',
                'channel_based' => true,
                'locale_based'  => true,
            ],            [
                'name'          => 'image',
                'title'         => 'Payment Method Icon',
                'type'          => 'image',
                'channel_based' => false,
                'locale_based'  => false,
                'validation'    => 'mimes:bmp,jpeg,jpg,png,webp',
                'info'          => 'Upload an icon to display in the payment methods selection page (recommended size: 100x50px)',
            ],
            [
                'name'          => 'gateway_logo',
                'title'         => 'Payment Gateway Logo',
                'type'          => 'image',
                'channel_based' => false,
                'locale_based'  => false,
                'validation'    => 'mimes:bmp,jpeg,jpg,png,webp',
                'info'          => 'Upload a logo to display in the Razorpay payment popup. If not uploaded, your site logo will be used automatically.',
            ],
            [
                'name'          => 'license_keyid',
                'title'         => 'License',
                'type'          => 'text',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => false,
            ],

            [
                'name'          => 'key_id',
                'title'         => 'key id',
                'type'          => 'text',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => true,
            ],            [
                'name'          => 'secret',
                'title'         => 'key secret',
                'type'          => 'text',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => true,
            ],
            [
                'name'          => 'theme_color',
                'title'         => 'Theme Color',
                'type'          => 'color',
                'default_value' => '#F37254',
                'channel_based' => false,
                'locale_based'  => false,
                'info'          => 'Choose the theme color for the Razorpay payment popup. Default is #F37254',
            ],
            [
                'name'          => 'active',
                'title'         => 'admin::app.configuration.index.sales.payment-methods.status',
                'type'          => 'boolean',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => true,
            ]
        ]
    ]
];
