Post Link Drop
=========

**Post Link Drop** is a WordPress plugin that creates a “link-in-bio” style image grid by importing Instagram posts and letting you link each item to any destination URL.

Features
--------

* Import posts from Instagram Business or Creator accounts
* Download and rehost images in your WordPress Media Library
* Link each imported item to any URL
* Responsive grid display via shortcode
* Automatic scheduled imports via WP-Cron
* Alt text generation from captions (editable)
* Supports images, video thumbnails, and carousel posts

Requirements
------------

* WordPress 5.0+
* PHP 7.4+
* Instagram Business or Creator account
* A Facebook Page connected to your Instagram account
* Meta (Facebook) Developer account

Installation
------------

1.  Upload the post-link-drop folder to /wp-content/plugins/
2.  Activate the plugin in **Plugins**
3.  Go to **Post Link Drop > Settings** to configure your Instagram connection

Getting Your Instagram API Credentials
--------------------------------------

Post Link Drop requires an Instagram **Access Token** and **User ID** to import posts.

### Step 1: Convert to a Business or Creator Account

If your Instagram account is personal, convert it:

1.  Open Instagram and go to your profile
2.  Tap the menu icon (three lines)
3.  Go to **Settings**
4.  Go to **Account** → **Switch to Professional Account**
5.  Choose **Business** or **Creator**
6.  Connect the account to a **Facebook Page** (required for API access)

### Step 2: Create a Meta (Facebook) App

1.  Go to [Meta for Developers](https://developers.facebook.com/) and log in
2.  Click **My Apps**
3.  Click **Create App**
4.  Enter an app name (example: “My Post Link Drop App”) and your contact email
5.  Under **Use Cases**, select: **Manage messaging & content on Instagram**
6.  Optionally assign your app to a business portfolio (recommended)
7.  Continue through the remaining prompts to reach your app dashboard

### Step 3: Configure Instagram Permissions + Connect Your Account

1.  In your Meta App dashboard, go to: **Use Cases**
2.  Locate **Instagram** and click **Customize**
3.  In the **Instagram Login** setup section, complete **Step 1** by adding all required permissions

#### Add Yourself as a Tester (Required)

To complete setup, you must add your Instagram account as a tester:

1.  In your Meta app dashboard, go to **App Roles** → **Roles**
2.  Click **Add People**
3.  Choose **Instagram Tester**
4.  Enter your Instagram username and select your account

#### Accept the Tester Invite in Instagram

1.  Open Instagram
2.  Go to **Settings**
3.  Go to **Website permissions** → **Apps and websites**
4.  Open the **Tester Invites** tab
5.  Accept the invite for your Meta app

### Step 4: Generate Your Access Token + Get Your User ID

1.  Return to your Meta app dashboard
2.  Go to **Use Cases** → **Instagram** → **Customize**
3.  Under setup **Step 2**, click **Add Account**
4.  Log in via the modal and authorize your Instagram account

✅ Notes:

* Your Instagram account must be connected to the correct Facebook account/business
* The Facebook user logged into Meta Developer must have full/manage access

1.  Click **Generate Token**
2.  Copy and save the token immediately**Important:** Meta will not show it again.

Also in Step 2, Meta will show your **Instagram User ID** (a numeric value).You need that for the plugin settings too.

**Reminder:** Access tokens must be refreshed before they expire.They typically last around **60–90 days**, so set a reminder to regenerate every **60 days**.

### Step 5: Enter Credentials in WordPress

1.  Go to **Post Link Drop > Settings**
2.  Paste your token into **Access Token**
3.  Paste your numeric ID into **Instagram User ID**
4.  Click **Test Connection**
5.  Click **Save Changes**

Configuration
-------------

Go to **Post Link Drop > Settings** to configure:

### Instagram Connection

* **Access Token**: Instagram Graph API access token
* **Instagram User ID**: Your numeric Instagram user ID
* **Test Connection**: Verify credentials

### Import Settings

* **Enable Auto-Import**: Toggle scheduled importing
* **Import Interval**:
  * Every 15 minutes
    
  * Every 30 minutes
    
  * Hourly (default)
    
  * Twice Daily
    
  * Daily
    
* **Import Limit**: Max posts per run (12, 24, 48, or 100)

### Manual Actions

* **Run Import Now**: Manually trigger an import (rate limited to once per 2 minutes)
* **Last Import**: Timestamp of most recent successful import
* **Last Error**: Displays the most recent import error (if any)

Usage
-----

### Displaying the Grid

Use this shortcode:

`[post_link_drop_grid]`

### Shortcode Attributes

* **count** (int, default: 12)Number of items to show.
* **status** (string, default: linked)Filters items by status meta value.
  * Use **all** to show everything **except** items with status hidden (hidden is always excluded).
* **columns** (int, default: 4)Number of grid columns. Used as the CSS variable --pld-columns.
* **gap** (string, default: 1rem)Space between grid items. Used as the CSS variable --pld-gap.
* **new\_tab** (boolean, default: true)Whether links open in a new tab.
  * When true, links get target="\_blank" and rel="noopener" and the aria-label notes it opens in a new tab.
* **orderby** (string, default: ig\_timestamp)_Currently not used_ in the query logic (the query always sorts by the ig\_timestamp meta value anyway).
* **order** (string, default: DESC)Sort direction. Only ASC is honored—anything else becomes DESC.
* **class** (string, default: "")Adds an extra CSS class to the grid wrapper (appended to pld-grid).

Managing Grid Items
-------------------

### Viewing Items

Go to **Post Link Drop > All Post Link Drop Items** to view imported posts.

### Item Columns

* **Thumbnail**: 60×60 preview image
* **IG Date**: Instagram post date
* **Destination URL**: Link target
* **Status**: linked / unlinked / hidden
* **Actions**: Hide/unhide controls

### Linking Items

#### Quick Link (Recommended)

1.  Find the item in the Items list
2.  Enter the destination URL in the **Destination URL** column
3.  Press **Enter** or click **Link**
4.  Status updates to **linked**

#### Full Edit

1.  Click an item to edit it
2.  In the **Linking** meta box:
  * Set **Status** (unlinked / linked / hidden)
    
  * Enter a **Destination URL**, **or**
    
  * Select an existing **Post/Page**
    
3.  Click **Update**

### Item Statuses

* **Linked**: Has a destination URL and appears in the grid
* **Unlinked**: Has no destination URL; excluded from default display
* **Hidden**: Explicitly excluded from the grid

### Editing Alt Text

Alt text is generated from the Instagram caption. To customize it:

1.  Edit an item
2.  Find the **Alt Text** field in the Details meta box
3.  Enter your custom alt text
4.  Click **Update**

Troubleshooting
---------------

### “Invalid Access Token”

* Your token may have expired — generate a new long-lived token
* Confirm you're using an **Instagram Graph API** token (not Basic Display)
* Verify the token has required permissions

### “Invalid User ID”

* Confirm you entered the numeric ID (not your username)
* It should be a long number like: 17841400000000000

### Images Not Importing

* Confirm the Instagram account is Business/Creator
* Confirm a Facebook Page is connected
* Confirm required permissions were added in Meta

### Grid Not Displaying

* Confirm you have items with **linked** status
* Confirm destination URLs are set
* Clear caching and refresh

### Import Rate Limited

* Manual imports are limited to once every 2 minutes
* Wait and try again, or enable auto-import

Frequently Asked Questions
--------------------------

### Can I use a personal Instagram account?

No. The Instagram Graph API requires a Business or Creator account connected to a Facebook Page.

### How often should I refresh my access token?

Long-lived tokens are typically valid for 60–90 days. Regenerate every 60 days to avoid interruptions.

### Will deleting an imported item delete the Instagram post?

No. Post Link Drop Items are local copies in WordPress.

### Can I import from multiple Instagram accounts?

Not currently. Post Link Drop supports one Instagram account per WordPress site.

### Are my images hosted locally?

Yes. Images are downloaded to your WordPress Media Library for faster loading and independence from Instagram.

### Does this work with videos?

Videos show as a thumbnail image. The video file itself is not imported.

### What happens with carousel posts?

The first image in the carousel is used as the grid thumbnail.

Changelog
---------

### 1.0.0

* Initial release
* Instagram import via Graph API
* Grid item management
* Shortcode display
* Scheduled auto-import
* Alt text generation

License
-------

GPL v2 or later

Support
-------

For issues and feature requests, please contact the plugin author.
