# ACME Client for concrete5

This is a concrete5 package that lets you generate certificates for websites, so that they can use the HTTPS protocol.

The certificates are generated via the ACME (Automated Certificate Management Environment) protocol, which is offered for free for example by [Let's Encrypt](https://letsencrypt.org/). 


## Features

This is a really feature rich package:

- the initial setup is really easy
- multiple ACME servers and accounts are supported
- adding ACME servers and accounts requires just a few clicks
- supports both [ACME v1](https://tools.ietf.org/html/draft-ietf-acme-acme-01) and [ACME v2 (aka RFC8555)](https://tools.ietf.org/html/rfc8555) protocols
- supports multiple domains, both local and remotes
- supports domain names with international characters
- supports creating HTTPS certificates for multiple domains
- lets you specify actions to be performed upon certificate generations/renewals (for example, it can save the newly generate certificate to a remote server by using SSH, and reload the web server configuration)
- offers a full set of CLI (Command Line Interface) commands so that you can create/edit/modify/delete/control almost everything via a terminal console
- the renewal of the certificates can be automated by adding a single line in your crontab
- you have full control of the revoked certificates
- supports checking if a certificate has been revoked


## Initial setup

This package can be installed in two ways:

1. if you have a composer-based concrete5 installation, just run
   ```
   composer require mlocati-concrete5-packages/acme
   ```
2. otherwise, you need to:
    - download this repository and save it under the `packages` folder, with a `acme` directory name
    - download the `composerpkg` command from [here](https://github.com/concrete5/cli)
    - from inside the downloaded `acme` folder, run this command:
         ```
         composerpkg install
         ```

Once you did that, just browse to the `/dashboard/extend/install` dashboard page.
You should see this:

![Dashboard installation](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/install-dashboard.png)

When you click the `Install` button, the ACME package performs some checks:

![Install checks](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/install-checks.png)

In case of problems, that page will tell you how to fix them.
Once all the tests pass, hit the `Install ACME` button.
After a while, the package gets installed, and you are asked if you want to easily create the first required data (the ACME server to be used, and an ACME account):

![Install wizard - ACME Server](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/install-wizard-server.png)

![Install wizard - ACME Account](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/install-wizard-account.png)

![Install wizard - Ready](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/install-wizard-done.png)


## Usage via the web interface

The main page of the ACME package is located in the dashboard, under `System & Settings` (that is, at the `/dashboard/system/acme` URL):


![Main menu](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/main-menu.png)


### ACME Servers and Accounts

You usually will have just one ACME Server and one ACME Account (the ones eventually configured at install time as described above).
Anyway, for testing purpouses you may want to use more.

Here's the interface you can use to add a new ACME Server:

![Add ACME Server](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/server-add.png)

ACME Servers are listed like this:

![ACME Server list](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/server-list.png)

You can add ACME Accounts (both new ones or accounts previously registered at the ACME Server):

![Add ACME Account](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/accunt-add.png)

You'll have a nice list of all the defined accounts:

![ACME Account list](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/account-list.png)


### Domains

In order to generate the HTTPS certificates, you need to define the domains they will be valid for, as well as a way to control them when the ACME Server will confirm that you own them.

At the moment you have two ways to control the ACME authorization process.

The first way only works for the currently running concrete5 installation:

![Local domain authorization](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/domain-add-http-intercept.png)

The second way allows you to control remote web servers (it's not required that they run concrete5, you only need SSH/FTP access to their web root directory):

![Remote domain authorization](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/domain-add-http-remote.png)

You'll also have a page where all the domains are listed:

![Domain list](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/domain-list.png)


### Certificates

Once you defined the domain(s) for which you want the HTTPS certificates, you can create a new certificate with an interface like this:

![Add certificate](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/certificate-add.png)

You can include one or more domains in the same certificate, which is very handy if you host more domains on the same server.

The package provides a page where all the certificates are listed, with the major details about them:

![Certificate list](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/certificate-list.png)


### Certificate Actions

When the HTTPS certificates have been issued by the ACME Server, you need to save them to file, and tell the webserver that it needs to refresh its configuration (so that the new certificates are served to the visitors of your sites).

You can define one or more actions to be performed upon certificate generation/renewal, on the local machine or on a remote machine:

![Certificate actions](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/certificate-actions.png)


### Certificate Generation, Renewal, and Actions Executions

A dashboar page will let you generate the certificates, renew them, and execute the actions (if any):

![Certificate renewal](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/certificate-renewal.png)


### Remote Servers

In order to control remote web sites (for the ACME Authorization process, to save the certificate files and to reload the web server configuration), you can define one or more Remote Servers.
You have great control about that; at the moment the supported protocols to connect to remote servers are:
- FTP (in Active or Passive mode)
- FTP over SSL (in Active or Passive mode)
- SFTP with username and password
- SFTP with username and a private key
- SFTP with an SSH Agent

(Please remark that the FTP protocol doesn't allow running commands, so you won't be able for example to ask the web server to reload its configuration)

![Add Remote Server](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/dashboard/remote-server-add.png)


## Usage via the Command Line Interface

There's a ton of CLI commands that lets you every operation you can do via the web interface:

![All CLI Commands](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/cli/all.png)

You can type the command name followed by `--help` to get the list of all the allowed arguments and options.

For example, to list the currently defined certificates, and to get the details of a specific certificate, you can use the `acme:certificate:list` command:

![Certificate List CLI Command](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/cli/certificate-list-and-details.png)

The most important CLI command (the only one that you'll need to run on a daily basis - maybe with crontab), is the `acme:certificate:refresh` command.
This command will renew the certificates, execute the actions, and send an email to one or more operators in case something goes wrong.

Here's a sample session:

![Certificate Refresh CLI Command](https://raw.githubusercontent.com/mlocati/concrete5_acme/assets/images/cli/certificate-refresh.png)
