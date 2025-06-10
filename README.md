# Bagisto Razorpay Payment Gateway
Razorpay is a popular payment gateway in India. This package provides strong support for users to integrate the Razorpay payment gateway into their Bagisto Laravel e-commerce applications.

---

## Licensing Information

You must take a license from the website [https://myapps.wontonee.com/login](https://myapps.wontonee.com/login), either trial or paid.

- **Trial License**: Works for 7 days.
- **Paid License**: Valid for 1 year and costs only ₹400.  
  Includes updates and support.

## How to Get a License Key

You can watch the video tutorial below to learn how to get a license key:

[![Watch the video](https://img.youtube.com/vi/E4NTZ4TyM5M/0.jpg)](https://youtu.be/E4NTZ4TyM5M?si=uIUXfeaj0ttH7VhC)


## Compatibility Notice
**<span style="color:red;">Support Bagisto v2.2. For Bagisto 2.1, you can downgrade the package to 4.2.2</span>**

**<span style="color:red;">From 15 January 2025, you must have a valid license key to use this extension. It costs only ₹400/year, including updates and support. Use this link to get your license key: [Get License Key](https://pages.razorpay.com/pl_PcXc750AtzmCEE/view)</span>**

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

4. Navigate to the `admin panel -> Configure/Payment Methods`, where Razorpay will be visible at the end of the payment method list.

5. **Configure License**: In the Razorpay payment method settings, enter your license key obtained from step 1.

6. Now run the following commands to optimize your application:
```sh
php artisan config:cache
php artisan optimize
```

## Troubleshooting

1. If you encounter an issue where you are not redirected to the payment gateway after placing an order and receive a route error, navigate to `bootstrap/cache` and delete all cache files.


For any help or customization, visit <https://www.wontonee.com> or email us <dev@wontonee.com>
