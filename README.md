# 🔒 YOURLS hCaptcha Plugin

<div align="center">

![YOURLS hCaptcha](https://img.shields.io/badge/YOURLS-hCaptcha-blue?style=for-the-badge&logo=shield)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

**Protect your YOURLS admin panel with hCaptcha verification!**

[Features](#-features) • [Installation](#-installation) • [Configuration](#-configuration) • [License](#-license)

</div>

---

## 📋 About

This plugin adds hCaptcha protection to your YOURLS admin login page, helping prevent automated attacks and brute-force attempts. The plugin features improved error handling, graceful degradation when keys aren't configured, and a user-friendly admin interface.

> **Note:** This is a fork of the original plugin by [RikoDEV](https://github.com/RikoDEV). The original repository is no longer available, but this fork includes improvements and continued maintenance.

## ✨ Features

- 🔐 **hCaptcha Integration** - Protect your admin login with hCaptcha verification
- ⚙️ **Easy Configuration** - Simple admin panel for managing hCaptcha keys
- 🛡️ **Graceful Degradation** - Plugin won't block login if keys aren't configured yet
- 🔍 **Error Handling** - Comprehensive error handling and debugging support
- 📱 **User-Friendly** - Clean, intuitive admin interface
- 🔒 **Secure** - Proper input sanitization and validation

## 🚀 Installation

### Step 1: Download the Plugin

Clone or download this repository to your YOURLS installation:

```bash
cd /path/to/yourls/user/plugins
git clone https://github.com/master3395/yourls-hcaptcha.git
```

Or manually copy the plugin folder to `/user/plugins/yourls-hcaptcha/`.

### Step 2: Activate the Plugin

1. Navigate to your YOURLS admin panel: `http://your-domain.com/admin/plugins.php`
2. Find **"Yourls-hCaptcha"** in the plugin list
3. Click **"Activate"**

### Step 3: Get hCaptcha Keys

1. Sign up for a free account at [hCaptcha.com](https://dashboard.hcaptcha.com/signup)
2. Create a new site in your hCaptcha dashboard
3. Copy your **Site Key** and **Secret Key**

### Step 4: Configure the Plugin

1. In your YOURLS admin panel, go to **Manage Plugins** → **Admin hCaptcha Settings**
2. Paste your **hCaptcha Site Key** in the first field
3. Paste your **hCaptcha Secret Key** in the second field
4. Click **"Save Changes"**

## ⚙️ Configuration

### Admin Settings Page

The plugin adds a configuration page under **Manage Plugins** → **Admin hCaptcha Settings** where you can:

- Set your hCaptcha Site Key (public key)
- Set your hCaptcha Secret Key (private key)
- View and update your configuration at any time

### Graceful Degradation

If the plugin is activated but hCaptcha keys are not configured, the plugin will:
- Allow normal login to proceed (won't block access)
- Log a warning message if YOURLS debugging is enabled
- Display the configuration page for easy setup

### Debugging

To enable debug logging for hCaptcha validation:

1. Enable YOURLS debugging in your `config.php`:
   ```php
   define('YOURLS_DEBUG', true);
   ```

2. Check your error logs for hCaptcha-related messages

## 📖 Usage

Once installed and configured:

1. Navigate to your YOURLS admin login page
2. The hCaptcha widget will automatically appear on the login form
3. Complete the hCaptcha challenge before logging in
4. If validation fails, you'll see an error message and can try again

## 🔧 Technical Details

### Requirements

- YOURLS 1.7+ or compatible version
- PHP 7.4 or higher
- Active internet connection (for hCaptcha API calls)

### Tested YOURLS Versions

This plugin has been tested and verified to work with the following YOURLS versions:

- ✅ **YOURLS 1.9.2** - Fully tested and working
- ✅ **YOURLS 1.9.1** - Fully tested and working
- ✅ **YOURLS 1.9.0** - Fully tested and working
- ✅ **YOURLS 1.8.3** - Fully tested and working
- ✅ **YOURLS 1.8.2** - Fully tested and working
- ✅ **YOURLS 1.8.1** - Fully tested and working
- ✅ **YOURLS 1.8.0** - Fully tested and working
- ✅ **YOURLS 1.7.x** - Compatible (should work, but not extensively tested)

> **Note:** While the plugin should work with YOURLS 1.7+, it has been primarily tested on YOURLS 1.8+ and 1.9+ versions. If you encounter any issues with older versions, please report them.

### Files Structure

```
yourls-hcaptcha/
├── plugin.php      # Main plugin file
├── hcaptcha.php    # hCaptcha validation logic
├── README.md       # This file
└── LICENSE         # MIT License
```

### Security Features

- Input sanitization using YOURLS functions
- Nonce verification for admin forms
- Secure API communication with hCaptcha
- Proper error handling without exposing sensitive data

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- **Original Author:** [RikoDEV](https://github.com/RikoDEV) - Original plugin creator (repository no longer available)
- **Fork Maintainer:** [master3395](https://github.com/master3395)

## 🔗 Links

- [YOURLS Official Website](https://yourls.org/)
- [hCaptcha Official Website](https://www.hcaptcha.com/)
- [hCaptcha Documentation](https://docs.hcaptcha.com/)

---

<div align="center">

**Made with ❤️ for the YOURLS community**

⭐ Star this repo if you find it useful!

</div>
