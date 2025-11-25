
# 📘 FOSSBilling – Freenom Registrar Module

This module adds **Freenom domain registration support** to FOSSBilling using the official Freenom API (`https://api.freenom.com/v2`).
It provides full support for **paid Freenom domains**, including search, registration, renewal, transfer, contact management, and nameserver updates.

> ### ⚠ IMPORTANT NOTE
>
> The Freenom API and the Freenom Reseller Program **do NOT support free domains** (`.tk`, `.ml`, `.ga`, `.cf`, `.gq`).
> Freenom only permits API operations for **PAID** or **DISCOUNTED** domains, and *free domains cannot be registered, renewed, or managed via API*.
> This is an official limitation of Freenom’s API and is not a bug in the module.

---

## ✨ Features

### ✔ Supported Operations

* Domain availability checks (PAID domains only)
* Domain registration (PAID)
* Domain renewal
* Domain transfer (incoming + outgoing actions)
* Domain modification (nameservers, forwarding, etc.)
* Retrieve domain info
* Contact:

  * Create / update
  * Delete (if unused)
  * Get info
  * List all contacts
* Nameserver glue:

  * Create
  * Delete
  * List

### ❌ Unsupported by Freenom API

* Registering *free* domains
* Managing *free* domains
* WHOIS privacy toggling (`enablePrivacyProtection`, `disablePrivacyProtection`)

  * Freenom’s privacy (ID Shield) behavior can only be set during registration when allowed
* Registrar Lock / Unlock

  * Freenom does not expose lock control for their TLDs via API

---

## 📦 Installation

1. Copy the module folder into:

   ```
   /library/Registrar/Adapter/Freenom.php
   ```
2. Ensure the file is readable by your web server user.
3. Log in to your FOSSBilling admin panel.
4. Navigate to:
   **Settings → Domain Registrars**
5. Enable **Freenom** and configure your credentials.

---

## 🔐 Configuration

You must enter the **exact credentials used on Freenom’s control panel**:

| Setting      | Description                |
| ------------ | -------------------------- |
| **Email**    | Your Freenom account email |
| **Password** | Your Freenom API password  |

These credentials are required for every API request.

**Note:**
Freenom does NOT use API keys — only email + password authentication.

---

## 📚 API Reference

This module implements all available Freenom endpoints according to the latest official API documentation, including:

* `/domain/search`
* `/domain/register`
* `/domain/renew`
* `/domain/getinfo`
* `/domain/modify`
* `/domain/delete`
* `/domain/restore`
* `/domain/upgrade`
* `/domain/transfer/*`
* `/nameserver/register`
* `/nameserver/delete`
* `/nameserver/list`
* `/contact/register`
* `/contact/delete`
* `/contact/list`
* `/contact/getinfo`

All responses are handled in **JSON**, and errors are mapped to FOSSBilling exceptions.

---

## 🚫 Free Domain Limitation (Important)

Freenom’s legacy “Free Domain” system (for `.tk`, `.ml`, `.ga`, `.cf`, `.gq`) is **not accessible** via API.
This includes:

* Cannot search for free domains
* Cannot register free domains
* Cannot renew free domains
* Cannot update nameservers on free domains
* Cannot manage ownership/contacts for free domains

The API **only supports domains that have a price**, whether full price or discounted.

---

## 📝 Notes

* Ensure your server supports outgoing HTTPS.
* The module disables WHOIS privacy toggle functions since the API does not support them.
* You must always create a Freenom **contact** before registering a domain — the module handles this automatically.

---

## 🤝 Contributions

Pull requests are welcome!
If you wish to extend functionality or improve Freenom API support, feel free to contribute improvements or open issues.

---

## 📄 License

This module is released under the **Apache 2.0 License**, compatible with FOSSBilling’s licensing model.

