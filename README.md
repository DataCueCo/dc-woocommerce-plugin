# DataCue for WooCommerce Integration

Learn how to connect DataCue for WooCommerce.

## Before You Start

**Here are some things to know before you begin this process.**

- For the most up-to-date install instructions, read [Connect or Disconnect DataCue for WooCommerce](https://help.datacue.co/woocommerce/installation). 

- This plugin requires you to have the [WooCommerce plugin](https://wordpress.org/plugins/woocommerce) already installed and activated in WordPress. 

- Your host environment must meet [WooCommerce's minimum requirements](https://docs.woocommerce.com/document/server-requirements), including PHP 7.0 or greater.

- We recommend you use this plugin in a staging environment before installing it on production servers. 

- DataCue for WooCommerce syncs your products, your customer’s first name, last name, email address, and orders.

- DataCue for WooCommerce also installs our Javascript library on your home page, product pages, category pages and search results page. The Javascript library personalizes your website content to each visitor's activity.

- Depending on your countries privacy laws, you may need to explicitly get permission from the user.

- DataCue for WooCommerce installs two widgets, banners and products. You may place banners on your home page, and the products widget on any page. The product widget should be placed on at least the home page and the product pages for best results.

## Installation and Setup
**Here’s a brief overview of this multi-step process.**

- Clone this repository to your local machine and run `composer up` from the folder
- Zip the whole folder using your favourite ZIP compression tool
- Install the plugin on your WordPress Admin site by clicking on **Plugins** > **Add New** > **Upload plugin** > **Choose File** > select the ZIP file you just created and click **Install Now**.
- Once installed, select **Activate Plugin**
- Connect the plugin with your DataCue API Key and Secret (you can find it on your dashboard) and press save.
- Depending on the size of your store the sync process can take a few mins to a few hours.

# Deactivate or Delete the Plugin
When you deactivate DataCue for WooCommerce, we remove all changes made to your store including the Javascript. We also immediately stop syncing any changes to your store data with DataCue.
To deactivate DataCue for WooCommerce, follow these steps.

1) Log in to your WordPress admin panel. 

2) In the left navigation panel, click **Plugins**, and choose **Installed Plugins**.

3) Click the box next to the DataCue for WooCommerce plugin, and click **Deactivate**.	

After you deactivate the plugin, you will have the option to **Delete** the plugin.