# Bagisto Razorpay Payment Gateway
Razorpay is a popular payment gateway in India. This package provides strong support for users to integrate the Razorpay payment gateway into their Bagisto Laravel e-commerce applications.

---

## Licensing Information

You must take a license from the website [https://myapps.wontonee.com/login](https://myapps.wontonee.com/login), either trial or paid.

- **Trial License**: Works for 7 days.
- **Paid License**: Valid for 1 year and costs only ₹800.  
  Includes updates and support.

## How to Get a License Key

You can watch the video tutorial below to learn how to get a license key:

[![Watch the video](https://img.youtube.com/vi/Xv1O4CteFs8/0.jpg)](https://www.youtube.com/watch?v=Xv1O4CteFs8)

## 🎯 Key Benefits

### For Store Owners
- ✅ **Complete Brand Control**: Custom logos and colors for professional appearance
- ✅ **Enhanced Security**: Improved CSRF protection and session handling
- ✅ **Easy Configuration**: User-friendly admin interface with clear options
- ✅ **Mobile Optimized**: Perfect experience across all devices

### For Customers
- ✅ **Professional Experience**: Modern, trustworthy payment interface
- ✅ **Clear Communication**: Progress indicators and security messaging
- ✅ **Fast Loading**: Optimized performance for quick payments
- ✅ **Consistent Branding**: Seamless integration with your store design


## Compatibility Notice
**<span style="color:red;">Support Bagisto v2.2. For Bagisto 2.1, you can downgrade the package to 4.2.2</span>**

**<span style="color:red;">From 15 January 2025, you must have a valid license key to use this extension. It costs only ₹800/year, including updates and support. Use this link to get your license key: [Get License Key](https://pages.razorpay.com/pl_PcXc750AtzmCEE/view)</span>**

## ✨ What's New in Latest Version

### 🎨 Enhanced Branding & Customization
- **Dual Logo System**: Separate logos for payment method selection and gateway popup
- **Custom Theme Colors**: Full color customization for payment interface
- **Smart Logo Fallback**: Automatic site logo integration when no custom logo is set
- **Modern UI Design**: Completely redesigned payment redirect page with animations

### 🔧 Technical Improvements
- **Enhanced CSRF Handling**: Better security with proper middleware configuration
- **Improved Session Management**: Reliable cart and currency handling during payments
- **Optimized Routes**: Streamlined routing structure for better performance
- **Better Error Handling**: Comprehensive error messages and user feedback

### 📱 User Experience Enhancements
- **Professional Loading Screen**: Elegant payment processing page with progress indicators
- **Security Messaging**: SSL badges and trust indicators for user confidence
- **Mobile Responsive**: Optimized design for all screen sizes
- **Clear Instructions**: Improved messaging throughout the payment flow

## Installation

1. **Get a License**: Visit [https://myapps.wontonee.com](https://myapps.wontonee.com) to obtain your Razorpay payment gateway license. Trial licenses work for 7 days only.

2. Use the command prompt to install this package:
```sh
composer require wontonee/razorpay
```

3. Publish the package assets:
```sh
php artisan vendor:publish --tag=razorpay-assets
```

4. Run the database migrations to create the necessary tables:
```sh
php artisan migrate
```

5. Navigate to the `admin panel -> Configure/Payment Methods`, where Razorpay will be visible at the end of the payment method list.

6. **Configure License**: In the Razorpay payment method settings, enter your license key obtained from step 1.

7. Now run the following commands to optimize your application:
```sh
php artisan config:cache
php artisan optimize
```

## 🚀 Features

### Payment Gateway Customization
- **Custom Theme Color**: Customize the Razorpay payment popup color to match your brand
- **Dual Logo Support**: 
  - Payment Method Icon for the checkout page
  - Payment Gateway Logo for the Razorpay popup
- **Smart Logo Fallback**: Automatically uses your site logo if no custom gateway logo is uploaded
- **Professional UI**: Modern, elegant payment redirect page with loading animations

### 🆕 Advanced Refund Management System
- **Admin Refund Interface**: Process refunds directly from Bagisto admin panel
- **Partial & Full Refunds**: Support for both partial and complete refund amounts
- **Real-time Status Updates**: Instant updates after refund processing
- **Refund History Tracking**: Complete audit trail of all refund transactions
- **Payment Data Storage**: Comprehensive storage of payment and refund information
- **Secure API Integration**: Direct integration with Razorpay Refund API
- **Interactive Dashboard**: Vue.js powered modern interface for refund management

### Advanced Configuration Options
- **Payment Method Icon**: Upload a custom icon for the payment methods selection page (recommended: 100x50px)
- **Payment Gateway Logo**: Upload a logo to display in the Razorpay payment popup
- **Theme Color**: Choose custom colors for the payment interface (default: #F37254)
- **Automatic Branding**: Uses your site's main logo as fallback for consistent branding

### Security & Performance
- **CSRF Protection**: Secure callback handling with proper middleware configuration
- **Session Management**: Proper cart and order handling during payment verification
- **Error Handling**: Comprehensive error messages and fallback mechanisms
- **Mobile Responsive**: Optimized for all device types

### User Experience
- **Loading Animations**: Professional payment processing page with progress indicators
- **Security Badges**: SSL encryption messaging for user confidence
- **Clear Instructions**: User-friendly messaging throughout the payment flow
- **Elegant Design**: Modern gradient backgrounds and card-based layouts

## Configuration

After installation, navigate to **Admin Panel → Configuration → Sales → Payment Methods → Razorpay** to configure:

1. **License Key**: Enter your Wontonee license key
2. **Razorpay API Keys**: Add your Razorpay Key ID and Secret
3. **Payment Method Icon**: Upload an icon for the checkout page
4. **Payment Gateway Logo**: Upload a logo for the Razorpay popup (optional)
5. **Theme Color**: Choose your brand color (default: #F37254)
6. **Activate**: Enable the payment method

## Troubleshooting

1. If you encounter an issue where you are not redirected to the payment gateway after placing an order and receive a route error, navigate to `bootstrap/cache` and delete all cache files.

2. **Theme Color Not Applied**: Clear configuration cache using `php artisan config:cache`

3. **Logo Not Displaying**: Ensure images are uploaded in supported formats (bmp, jpeg, jpg, png, webp)

4. **Payment Callback Issues**: Verify that the `/razorpaycheck` route is accessible and not blocked by firewalls

## 💬 Special Discount Offer

🎉 **Get Exclusive Discounts!** Contact us on WhatsApp for special pricing:

**WhatsApp**: [+91 9711381236](https://wa.me/919711381236)

- Bulk license discounts available
- Custom development services
- Priority support options
- Extended license terms

## Support & Contact

For any help or customization:
- 🌐 **Website**: [https://www.wontonee.com](https://www.wontonee.com)
- 📧 **Email**: [dev@wontonee.com](mailto:dev@wontonee.com)
- 💬 **WhatsApp**: [+91 9711381236](https://wa.me/919711381236)
- 🎥 **Video Tutorial**: [Watch Installation Guide](https://www.youtube.com/watch?v=Xv1O4CteFs8)

---

**Made with ❤️ by [Wontonee](https://www.wontonee.com)**
