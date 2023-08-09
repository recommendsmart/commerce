# Commerce License

Commerce License allows the creation of products that sell access to some
aspect of the site. This could be a role, publication of a node, and so on.

This access is controlled by a License entity, which is created for the user
when the product is purchased.

The nature of what a License entity grants is handled by License type plugins.
Each License entity will have one License type plugin associated with it.

A product variation that sells a License will have a configured License type
plugin field value. This acts as template to create the License when a user
purchases that product variation.

## REQUIREMENTS

This module requires the following modules:

- [Commerce](https://drupal.org/project/commerce)
- [Advanced Queue](https://drupal.org/project/advancedqueue)

In order to provide licenses that automatically renew with a subscription, this
module optionally integrates with:

- [Commerce Recurring](https://drupal.org/project/commerce_recurring)

The following patches are required for core versions prior to 9.3.x:

- <https://www.drupal.org/project/drupal/issues/2911473#comment-12676912>
  Selected yet disabled individual options from checkboxes element don't persist
  through save.

The following patches are recommended:

- <https://www.drupal.org/project/commerce/issues/2930979> Don't show the
  'added to your cart' message if the item quantity is unchanged.

## INSTALLATION

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/docs/extending-drupal/installing-modules for
further information.

## CONFIGURATION

To create products that grant licenses that expire:

1. Configure (or create) a checkout flow
   at `admin/commerce/config/checkout-flows`
   - This flow must set "Guest checkout: Not allowed" for the "Login or continue
     as guest" pane.
2. Configure (or create) an Order Type at `admin/commerce/config/order-types`
   - This must set the Checkout Settings/Checkout flow to the one configured in
     step (1).
3. Configure (or create) an Order Item Type
   at `admin/commerce/config/order-item-types`
   - Its "Order Type" must be the one configured in step (2).
   - It must enable the trait "Provides an order item type for use with
     licenses".
4. Configure (or create) a Product Variation Type
   at `admin/commerce/config/product-variation-types`
   - Its "Order Item Type" must be the one configured in step (3).
   - It must enable the trait "Provides a license".
5. Configure (or create) a Product Type at `admin/commerce/config/product-types`
   - Its product variation type must be the one configured in step (4).
6. Create one or more products at `admin/commerce/products`
   - Its product type must be the one configured in step (5).
   - In the product variation, configure:
     - License Type
     - License Expiration

To create products that grant licenses that renew with a subscription:

1. Configure (or create) a checkout flow
   at `admin/commerce/config/checkout-flows`
   - This flow must set "Guest checkout: Not allowed" for the "Login or continue
     as guest" pane.
2. Configure (or create) an Order Type at `admin/commerce/config/order-types`
   - This must set the Checkout Settings/Checkout flow to the one configured in
     step (1).
3. Configure (or create) an Order Item Type
   at `admin/commerce/config/order-item-types`
   - Its "Order Type" must be the one configured in step (2).
   - It must enable the trait "Provides an order item type for use with
     licenses".
4. Configure (or create) a Product Variation Type
   at `admin/commerce/config/product-variation-types`
   - Its "Order Item Type" must be the one configured in step (3).
   - It must enable the trait "Provides a license".
   - It must enable the trait "Allow subscriptions".
5. Configure (or create) a Product Type at `admin/commerce/config/product-types`
   - Its product variation type must be the one configured in step (4).
6. Configure (or create) a Billing Schedule
   at `admin/commerce/config/billing-schedules`
7. Create one or more products at `admin/commerce/products`
   - Its product type must be the one configured in step (5).
   - In the product variation, configure:
     - License type
     - License expiration
       - The expiration should be 'Unlimited', as the subscription controls
         this.
     - Subscription type
       - Set to 'License'
     - Billing schedule
       - Choose a billing schedule set up in step (6)

## KNOWN ISSUES AND LIMITATIONS

[Issue Tracker](https://www.drupal.org/project/issues/commerce_license?version=8.x)

This module is still incomplete, and has the following limitations:

- The admin forms to create/edit licenses are not yet complete. They should only
  be used by developers who know what they are doing. Changing values here can
  break things!
