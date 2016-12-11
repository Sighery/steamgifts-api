# SteamGifts API

This is an API I'm developing for the [SteamGifts](https://www.steamgifts.com/) website. For now I'm the only developer for it. This API will be hosted on [api.sighery.com](http://api.sighery.com).

I'm using [simple_html_dom](http://simplehtmldom.sourceforge.net/) for parsing SG's HTML pages, and the [Slim framework](https://www.slimframework.com/) for hosting the API and all the routes. For the database I'm using MySQL and PHPMyAdmin. I'm also using an `.htaccess` file with ModRewrite to rewrite URLs for syntactic sugar.

I'll be uploading more information about the different stuff I'm using and how, like the DB schema as I develop it.

---

There will be multiple endpoints in charge of different sections. For now these are the ones available:

* **IUsers**: Stands for *InfoUsers*. Its methods will all be related to getting information about users.

An example request for profile info of an user would be:
```
http://api.sighery.com/SteamGifts/IUsers/GetUserInfo/?user=Sighery

// The syntax for a request is:
http://api.sighery.com/Website/Endpoint/Method/?args=values
```

There will be more info and documentation on the index page of the API for every endpoint and methods.

**PLEASE NOTE that the requests ARE CASE-SENSITIVE. Typing `steamgifts` instead of `SteamGifts` will give back a 404 Not Found error.**
