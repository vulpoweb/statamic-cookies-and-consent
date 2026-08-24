# Security Policy

## Supported versions

The latest minor release receives security fixes.

## Reporting a vulnerability

Email **security@vulpo.be** rather than opening a public issue. Include the affected version, what an attacker can do, and a reproduction if you have one. You will get an acknowledgement within three working days.

Please do not test against sites you do not own.

## Scope notes

Two things about this addon are deliberate, documented, and not vulnerabilities on their own:

- The **service script field injects raw markup**. Anyone who can edit addon settings can run JavaScript on every page. That is the feature. A report is in scope if the field can be reached or written by someone who cannot already edit addon settings.
- The **consent cookie is exempt from Laravel's cookie encryption**, because the front-end runtime writes it. It holds a revision, a timestamp and the granted handles, and nothing else. A report is in scope if that cookie can be used to do more than allow or refuse a configured service — for example if a crafted value can influence what markup is rendered.

Also in scope: the consent cookie failing to keep a refused service from running, the banner leaking a visitor's decision into a cacheable response, and the Consent Mode mapping granting a key its category was not given.
