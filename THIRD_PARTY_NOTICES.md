# Third-party notices

This project runs on top of open-source components. Each is used under its own
license; the primary licenses are collected in [`LICENSES/`](LICENSES/). Exact
versions and image digests are recorded in
[`docs/build-notes/runtime-and-licenses.md`](docs/build-notes/runtime-and-licenses.md).

| Component | Role | License |
|---|---|---|
| Magento Open Source | E-commerce platform (store + admin) | OSL-3.0 / AFL-3.0 |
| MariaDB | Relational database | GPL-2.0 |
| OpenSearch | Catalog search engine | Apache-2.0 |
| OpenSearch `analysis-icu`, `analysis-kuromoji` plugins | Text analysis | Apache-2.0 |
| Node.js | ERP Refund API stub runtime | MIT (+ others) |
| nginx | Web server | BSD-2-Clause |
| PHP | Application runtime | PHP License v3.01 |
| Composer | Dependency manager (build time only) | MIT |
| PHPUnit | Test framework (dev dependency) | BSD-3-Clause |

The `Acme_SellerRefund` module and all assignment scaffolding in this repository
are original work provided under the terms in [`LICENSE`](LICENSE).
