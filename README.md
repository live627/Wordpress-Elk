# Wordpress Bridge
[![MIT license](http://img.shields.io/badge/license-MIT-009999.svg)](http://opensource.org/licenses/MIT)
[![GitHub issues](https://img.shields.io/github/issues/live627/Wordpress-Elk.svg)](https://github.com/live627/Wordpress-Elk/issues)
[![Latest Version](https://img.shields.io/github/release/live627/Wordpress-Elk.svg)](https://github.com/live627/Wordpress-Elk/releases)
[![Total Downloads](https://img.shields.io/github/downloads/live627/Wordpress-Elk/total.svg)](https://github.com/live627/Wordpress-Elk/releases)
[![Support](https://supporter.60devs.com/api/b/axlsj1o8o0amepfrr5eqlcjza)](https://supporter.60devs.com/give/axlsj1o8o0amepfrr5eqlcjza)

## Introduction:
Bridge logins between WordPress and ElkArte.

- Setup this mod at Administration Center » Wordpress Bridge.
- Logins are synchronized with your WordPrress site once users log into the forum.
  - The inccluded WP plugin will redirect users to the forum if they try to register or login to the blog site.
    - The single file `elk-wp-auth.php` goes into WP's plugins directory
    - It should be activated within the ElkArte site.
 - The bridge will automatically create new users to try to keep everything in sync.

Note that there is a conflict because both ElkArte and WordPresss try to load the same password library into the same namespace. This can easily be remedied by adding a small code snippet.

In ./wp-includes/class-phpass.php, find

```
class PasswordHash {
```

and replace it with the following


```
if (class_exists('PasswordHash')) return;
class PasswordHash {
```

I recommend doing this before installing the bridge, to avoid said conflict if you forget this later. It simply checks if the class is already loaded, and skips loading if it's already in memory.

Requires PHP 5.4 or newer to run

Ask about any questions and please donate if you can.
