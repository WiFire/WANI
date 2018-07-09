# WANI
Instructions for integrating your networks with WiFire, the first app compliant with TRAI's WANI architecture.
This guide will be updated to reflect any changes in the spec or in WiFire's implementation.

[**Get WiFire for Android here!**](https://play.google.com/store/apps/details?id=com.mobstac.wildfire&hl=en)

**Note**: WANI is currently only implemented on the Android version of WiFire, not on iOS.

## Registering your networks
WiFire uses the WANI registry to suggest networks for users to connect to. So the first step is to ensure that you have registered your company and all your networks with TRAI. **Note**: URLs for the WANI registry should be accessed with HTTP instead of HTTPS. There is a bug on TRAI's end that prohibits HTTPS access.

**Providers:** http://trai.gov.in/sites/default/files/wani_providers.xml

This URL lists all network providers and all app providers. You will need to sync app providers from here and store their information. Each network provider in the registry is assigned a URL where their networks are listed. The following URL is for the provider i2e1: 

**Networks:** http://trai.gov.in/wani/registry/wani_providers/581e27be-9def-11e7-lbc4-cec778b6b50a/wani_aplist.xml

When you register your networks with TRAI, they will be added to a similar list. Please ensure that you provide accurate geolocation, SSID, BSSID, and CPURL. WiFire will attempt WANI login only if the network's SSID and BSSID matches exactly with a network present in the WANI registry.

WiFire syncs changes in the registry once a day. Any network present in the registry will show up in the list of in-range networks with a "TRAI" badge.

## Reference PHP implementation

Feel free to copy our [**sample PHP code**](https://github.com/WiFire/WANI/tree/master/php) for WANI compliance to accelerate your development and quickly add support for WANI in your captive portals.

## Logging in with WiFire

### 1. Requests from WiFire
When you initiate login on a "TRAI" network through WiFire, it makes a GET request on the **cpUrl** declared for that network in the WANI registry. The request is as follows:

https://your.declared/cpUrl?waniapptoken=app-provider-id|encrypted-app-data

**waniapptoken:** This token should not be modified in any way. It consists of two parts below separated by a pipe (|):
- The provider ID. You use this to identify the app making the request and fetch its information from the WANI registry, including its declared **authUrl**.
- Private data from WiFire which is only readable to WiFire's auth server.

Do not send any response to WiFire yet. You should return a response only after the process described below is completed. Please see our [sample PHP code](https://github.com/WiFire/WANI/tree/master/php) for how this is done.

### 2. Authenticating with WiFire
Determine the **authUrl** for WiFire, and send it a GET request as below. Please ensure that this URL is whitelisted on your network.

https://wifireauth.mobstac.com/wani/v1/login?wanipdoatoken=your-provider-id|your-key-exp|encoded-waniapptoken

**wanipdoatoken**: This token should be constructed by joining the three parts below with a pipe (|):
- Your provider ID.
- The expiry of your latest key pair. This should match with the expiry of one of your keys declared in the WANI registry.
- **UPDATE: RSA Encryption of waniapptoken before base64 encoding according to the spec is now required (starting 9 July 2018)** 
- Base64 encoding of the entire RSA encrypted string obtained from the previous step

You will need to use RSA encryption using RSA_PKCS1_PADDING. In Node.JS, for e.g., you can use the crypto.privateEncrypt function passing in 256 bytes at a time. This is how we decrypt it:

```
    // buf is the base64 decoded string
    var decrypted = '';
    for (var i = 0; i < buf.length; i += 256) {
        decrypted += crypto.publicDecrypt({key: key, padding: constants.RSA_PKCS1_PADDING}, buf.slice(i, i + 256));
    }
```

You will then receive a response from our auth URL with status 200 and JSON in the below format:

```
{
    "ver": "1.0",
    "app-provider-id": "581eaafe-9def-11e7-abc4-cec278b6b50k",
    "app-provider-name": "WiFire",
    "timestamp": "20171221071500",
    "username": "919876543210",
    "payment-address": "",
    "devices": ["A1B2C3D4E5F6"],
    "key-exp": "20180731",
    "signature": "..."
}
```

Any status code other than 200 implies failure, and some error detail text will be sent in the response.

### 3. Responding to WiFire
After you get a successful response from our auth URL, you should allow access on your network for the MAC IDs in the **devices** array. You should take any other steps necessary to log users into your network at this point.

Finally, you can respond to the WiFire app's initial request in one of 3 ways:
- If you need WiFire to open a payment page, send a response with status 200 and a JSON body containing the following field:
```
{
  ...
  "paymentUrl": "https://your.payment/page",
  ...
}
```
- Any status 200 response that doesn't contain the **paymentUrl** JSON field will be treated as login success, and the user will be notified that they are online.
- Any status other than 200 will be interpreted as login failure.

That's it!

## Dealing with dynamic parameter requirements
It may be the case that your existing captive portal system requires dynamic parameters for logging in, such as the router's MAC ID, connection timestamp, sequence number etc. One way deal with this is as follows:

1. Your declared CPURL should not be the actual captive portal URL, but a wrapper over it.
2. Handle steps 1 & 2 of WiFire login as described above.
3. Then make an AJAX request from the CPURL to your actual captive portal along with any dynamic parameters that you need.
4. Perform the actual login for your network on the captive portal and return a response to the CPURL.
5. Finally, return a response from the CPURL to the WiFire app as described in step 3 of WiFire login.

## FAQs

1. **Any latency requirements for logging in?** The WiFire app expects a response from the captive portal within 5 seconds at the most, failing which the user will be informed that login did not succeed. Our auth URL responds in under 100ms most of the time.

2. **Why isn't my network showing up in WiFire?** WiFire needs an exact match on SSID (case-sensitive) & BSSID with a network present in the WANI registry. Failing this, it will neither show your network nor attempt WANI login on it. Also, the network's geolocation declared in the WANI registry must be reasonably close to its real location, since our app only syncs networks within a few kilometers of the user.

3. **Do I need to verify the signature in the auth response?** We recommend skipping this step for now. TRAI's spec recommends a non-standard signature method, and we hope to have a discussion with them about this. This guide will be updated on this step in the near future.
