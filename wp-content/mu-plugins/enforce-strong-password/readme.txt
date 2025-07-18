=== Enforce Strong Password ===
Contributors: zaantar
Tags: strong, password, force, enforce
Donate link: http://zaantar.eu/financni-prispevek
Requires at least: 3.5.1
Tested up to: 3.5.1
Stable tag: 1.3.5

Forces all users to have a strong password when they're changing it on their profile page.

== Description ==

Forces all users to have a strong password when they're changing it on their profile page. If user enters a weak password an error message is displayed.

It uses the same algorithm to determine password strength as WordPress.

On multisite, network administrator can define required password strength (Network Admin --> Settings --> Enforce Strong Passwords). On single site it's admin has this capability (Options --> Enforce Strong Passwords).

Developed for private use, but has perspective for more extensive usage. I can't guarantee any support in the future nor further development, but it is to be expected. Kindly inform me about bugs, if you find any, or propose new features: [zaantar@zaantar.eu](mailto:zaantar@zaantar.eu?subject=[enforce-strong-password]).

== Installation ==

* Install as usual and activate

That's it.

== Frequently Asked Questions ==

No questions asked yet. [Ask.](mailto:zaantar@zaantar.eu?subject=[enforce-strong-password])

== Changelog ==

= 1.3.5 =
* Added Dutch language files (thanks to Ronald de Caluwé)

= 1.3.4 =
* Fix warnings while updating user profile.

= 1.3.3 =
* Use validate_password_reset hook introduced in WordPress 3.5.
* Fix compatibility issue with WordPress 3.5.1 while resetting password.
* Require WordPress 3.5.1.

= 1.3.2 =
* enforce strong password also when resetting passwords (thanks to Daniel Henrique Alves Lima)

= 1.3.1 =
* minor text changes
* added pt_BR translation (thanks to Daniel Henrique Alves Lima)

= 1.3 =
* code refreshed (rewritten into php class)
* options page for admin resp. network admin
* ability to select minimal required password strength

= 1.2 =
* uploaded to wordpress.org
* i18zed
* added czech translation

= 1.1 =
* error message update

= 1.O =
* first version
