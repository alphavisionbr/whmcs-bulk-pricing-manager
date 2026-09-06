# Relatório de auditoria da release

## Versão

Alphavision WHMCS Bulk Pricing Manager 1.0.2

## Verificações executadas

* análise sintática dos arquivos PHP com parser independente;
* validação sintática do JavaScript;
* conferência estrutural do CSS;
* consistência do versionamento interno;
* inspeção da estrutura do pacote;
* teste de integridade do ZIP;
* busca por rotinas de alteração de faturas;
* busca por caracteres de travessão nos arquivos entregues.
* verificação da migração incremental do schema em análise estática;
* conferência da contextualização dos IDs nas tabelas;
* conferência dos campos de transparência do histórico.
* conferência do bloco de apoio em todas as telas administrativas.

## Limite da auditoria

O ambiente de construção não contém PHP nem uma instalação WHMCS. Portanto, a release ainda exige o roteiro de `docs/HOMOLOGACAO.md` antes do uso em produção.


## Status posterior da versão

Após a auditoria estática descrita neste documento, a versão **1.0.2** foi testada, validada e homologada em ambiente WHMCS antes de sua publicação pública.

A observação do relatório original sobre a necessidade de homologação descreve o estado da release no momento em que aquela auditoria foi produzida.
