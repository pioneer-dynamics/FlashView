# F.A.Q.

## Why Use {CONFIG:app.name}?

When you share passwords or private links through email or chat, copies of that information can end up stored in multiple locations, creating potential security risks. Using a {CONFIG:app.name} link ensures the information is accessible for a single viewing only, preventing it from being read by anyone else afterward. This provides a secure way to share sensitive information, knowing it will only be seen by the intended recipient. Think of it as a self-destructing message for added peace of mind. Moreover, the contents of the mssage are end-to-end encrypted (i.e., even we cannot see the contents of the message because the message is alreeady encrypted in the browser before it is sent to us).

## Can I retrieve a secret that has already been shared?
No, once a secret is retrieved by the recipient's browser, it’s permanently deleted even before it is decrypted by the browser. Hence, even if the recipient enters the wrong password the message is deleted and can't be retrieved again.

## Shouldn't the recipient be allowed to re-try with the correct password if the firsst attempt fails?
Since the data we send to the browser is end-to-end encrypted by the sender, we cannot verify the password's correctness on the server side (unless the password is sent to us, but that would defeat the purpose of end-to-end encryption). If the browser successfully decrypts the message, we cannot know for sure unless the browser informs us. Allowing this type of communication is risky because a man-in-the-middle attacker could "fake" a response indicating that the password is incorrect, providing false information and preventing the message from being deleted. To mitigate this risk, we delete the message as soon as the encrypted data is delivered to the browser.

## What is the difference between anonymous users and free accounts?
Anonymous users can create secrets that last up to 7 days, with a maximum size of 160 characters (size of an SMS). Free account holders enjoy additional benefits: secrets can last up to 14 days and can be up to 1000 characters. Account holders can also burn a secret before it can be retrieved.

## Why would I trust you?
Your trust is important, and we understand the need for privacy and security. Here's why you can trust our system:

1. **We cannot access your information:** The secrets are end-to-end encrypted. So, even if we wanted to, we have no access to the secret.
1. **Open-source:** Our code is fully <a href="https://github.com/pioneer-dynamics/One-Time-Share" target="_blank">OpenSource!</a>, allowing you to review it or run your own instance if you prefer, ensuring transparency and trust.
1. **Industry-standard security:** We follow best practices for security, using HTTPS for all connections and encrypting stored data at rest to protect your information.

These practices are in place to ensure your privacy and data security are protected at all times.

## How safe is sharing a secret with {CONFIG:app.name}?
The secret message is encrypted on your browser. The passphrase and the plaintext secret message are never sent to {CONFIG:app.name}. Only the encrypted message is sent to the server. So we have no means to decrypt the message. For the same reason, a hacker who gains complete access to all our systems will still be unable to decode the secret message.

## How do you compare with other providers, like onetimesecret.com?
Most other providers like onetimesecret.com send the secret and password as plaintext to the server and the server does the encryption. Though the good people at onetimesecret.com do not misuse this, a man in the middle could still grab the secret message. {CONFIG:app.name} encrypts the message on your browser before sending it to the server. This way we, or a man in the middle, do not know what the secret is or what the passphrase to decrypt it is.

## How secure is the encryption?
The encryption is based on AES-256-GCM which is one of the most secure encryption algorithms available to mankind. With the existing technology, it will take millions of years for someone to crack the encryption.

## Why don't you add the passphrase to the link like other providers?
While it is convenient for the end user to encode the passphrase into the link, it would also mean that our server receives the passphrase and so does a man in the middle. By not including the passphrase in the link, we have completely cut off ourselves and any man in the middle from being able to retrieve the secret message.

## How do I delete the secret message?
The secret message is deleted when it reaches the expiry set while generating the secure link, or on the first attempt to retrieve the message - whichever comes first. If you wish to delete the secret before it is retrieved, you could visit the same link, give any random password and press retrieve to delete the message, or, if you have an account, you could go to [My Secrets]({ROUTE:secrets.index}) and click on "Burn" against the Message ID.

## How long will the secret be available?
The secret message is deleted when it reaches the expiry set while generating the secure link, or on the first attempt to retrieve the message - whichever comes first.

## What would be some of the use cases?
For sending passwords, OTP, API keys, etc, or sharing your Netflix credentials with famliy.
To confess to your secret crush.
Tell your kids about your grandfather's secret treasure stash.
Anything you can think off...

## How do you avoid harassment and other illegal uses of the system?
We log the IP address and the time at which a link is generated and retrieved. This log is kept for 732 days after which they are permanently deleted as well. If a legal authority produces a court order to request details about a message, we will provide them with the IP address and the time at which the link was generated and accessed as long as it has not yet been pruned by the system.

## Isn't that a privacy concern?
All secret messages are stored encrypted and encryption occurs in the browser before it is sent to us. So the content of the message is still secure and of no use to anyone except the recipient. We will not be able to retrieve the message content even if we wanted to. We will only share the metadata (IP address and time it was created, and the IP address and time it was retrieved) with legal authorities and only if they request it with a proper court order that relates to an investigation that is related to illegal use like harassment or terrorism. In all other cases, no metadata will be shared.

## How is the metadata stored?
The IP Addresses are stored encrypted using the AES-256-CBC algorithm with an "application key". This key is not stored in the database and is only stored on our application server. Hence a hack into our database will not reveal the metadata. The time at which a message was created or retrieved is not stored encrypted.

## How can legal authorities contact you with a court order?
If you are a legal authority, you can email us at {CONFIG:support.legal}. The request should have the retrieval URL for the message, a scanned copy of the notarized court order, and the reason for the request. To speed up the process (that is to help us validate the authority of the court order) we recommend that the email be sent from the registered domain name for the court or the legal authority and be signed using a valid digital signature.

## Are there any limits to the number of secret links I can generate?
We rate limit the number of times an anonymous user can generate links to avoid abuse. Currently, anonymous users can create 3 links per minute and 10 links per day. [Login]({ROUTE:login}) or [create a free account]({ROUTE:register})! to increase the limit. Authenticated users on the free plan can create 60 links per minute. Check out our [paid plans]({ROUTE:plans.index}) if you need to send larger messages.