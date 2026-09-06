# Remoção

A desativação preserva as tabelas de auditoria:

* `mod_av_bulkpricing_batches`
* `mod_av_bulkpricing_items`

Essa decisão impede a destruição acidental do histórico.

Para remoção definitiva, desative o addon, exporte os registros necessários, remova manualmente as tabelas e depois exclua `modules/addons/alphavision_bulk_pricing_manager`.

