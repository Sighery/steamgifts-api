// ==UserScript==
// @name         Sighery's APIs JSON prettifier
// @author       Sighery
// @description  Prettifies the JSON responses from api.sighery.com
// @version      0.1
// @icon         https://raw.githubusercontent.com/Sighery/Scripts/master/favicon.ico
// @downloadURL  https://raw.githubusercontent.com/Sighery/steamgifts-api/master/prettifier-script/APIPrettify.user.js
// @updateURL    https://raw.githubusercontent.com/Sighery/steamgifts-api/master/prettifier-script/APIPrettify.meta.js
// @supportURL   https://github.com/Sighery/steamgifts-api/issues
// @namespace    Sighery
// @match        http://api.sighery.com/*/*/*
// @grant        none
// ==/UserScript==

var elem = document.body.children[0];
elem.innerHTML = JSON.stringify(JSON.parse(elem.innerHTML), null, 4);
