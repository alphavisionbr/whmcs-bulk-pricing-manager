# Alphavision® WHMCS Bulk Pricing Manager

Administrative addon for **safe, selective, simulated and auditable** recurring price updates in WHMCS.

**Current version:** 1.0.2  
**Status:** stable, tested and validated release

[Versão em Português](README.md)

## About

**Alphavision® WHMCS Bulk Pricing Manager** was developed to manage contracted recurring prices in WHMCS without blindly applying bulk changes.

The module lets administrators filter and select the exact records to be changed, simulate the result before writing data, review Before and After values, confirm the operation and maintain an auditable history by batch and by item.

## Features

- Products and Services, Domains and Addons.
- Individual and bulk selection on the current page.
- Specific filters for each record type.
- Synchronization with the current WHMCS catalog price.
- Increase or decrease by percentage.
- Increase or decrease by fixed amount.
- Set an absolute new value.
- Mandatory simulation before any change.
- Price revalidation at confirmation time.
- Batch and item history.
- Protected rollback against later changes.
- Individual processing per item.
- Existing invoices are never modified.
- Administrative audit trail and financial impact per batch.

## Compatibility

- WHMCS 9.0.5 or later within the 9.x series
- PHP 8.3, 8.4 and 8.5, subject to the compatibility matrix of the installed WHMCS version
- MySQL or MariaDB supported by WHMCS
- Blend administrative theme

The module uses the Capsule instance already loaded by WHMCS and does not require Composer, external libraries or third-party service connections.

## Installation

1. Download the installable ZIP from the corresponding GitHub Release.
2. Extract the ZIP.
3. Upload the `modules` directory to the root of the WHMCS installation, merging it with the existing structure.
4. Go to **System Settings > Addon Modules**.
5. Activate **Alphavision WHMCS Bulk Pricing Manager**.
6. Configure the administrator roles allowed to access the addon.
7. Open **Addons > Preços em Massa**.

Operational module path:

```text
modules/addons/alphavision_bulk_pricing_manager/
```

The current administrative interface is in Brazilian Portuguese.

## Safety model

The module is designed to reduce the risk of accidental financial changes.

Controls include:

- administrative-only access;
- native WHMCS Access Control;
- CSRF token bound to the administrative session;
- mandatory simulation;
- revalidation before writing;
- simulation reuse protection;
- per-item transactions;
- administrator, value and result audit trail;
- rollback conditioned on the current record state;
- no routines that modify existing invoices.

Do not disclose vulnerabilities through public Issues. See [SECURITY.md](SECURITY.md).

## Documentation

- [CHANGELOG.md](CHANGELOG.md): version history.
- [docs/COMPATIBILIDADE.md](docs/COMPATIBILIDADE.md): compatibility matrix.
- [docs/HOMOLOGACAO.md](docs/HOMOLOGACAO.md): validation checklist.
- [docs/REMOCAO.md](docs/REMOCAO.md): complete removal.
- [docs/SEGURANCA.md](docs/SEGURANCA.md): operational security practices.
- [docs/audits/](docs/audits/): preserved audit reports.
- [docs/releases/](docs/releases/): published release notes.

## Contributing

Contributions are welcome.

You may study, modify and propose improvements through Issues and Pull Requests. Before contributing, review the contribution guidelines and Code of Conduct provided by the Alphavision® GitHub organization.

## Support

See [SUPPORT.md](SUPPORT.md) before opening a support request.

## License

This project is distributed under the **MIT License**.

SPDX identifier:

```text
MIT
```

See [LICENSE](LICENSE).

## Alphavision®

Developed and maintained by **Alphavision®**.

**Project:** https://github.com/alphavisionbr/whmcs-bulk-pricing-manager  
**Website:** https://alphavision.com.br  
**Contact:** contato@alphavision.com.br

---

**Alphavision®**  
Web Platforms, Cloud Infrastructure & Digital Solutions
