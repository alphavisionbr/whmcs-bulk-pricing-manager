# Alphavision® WHMCS Bulk Pricing Manager

Addon administrativo para atualização **segura, seletiva, simulada e auditável** de preços recorrentes no WHMCS.

**Versão atual:** 1.0.2  
**Status:** versão estável, testada e homologada

[English version](README.en.md)

## Sobre

O **Alphavision® WHMCS Bulk Pricing Manager** foi desenvolvido para administrar preços recorrentes contratados no WHMCS sem realizar alterações cegas em massa.

O módulo permite filtrar e selecionar exatamente os registros desejados, simular o resultado antes da gravação, revisar os valores **Antes e Depois**, confirmar a operação e manter um histórico auditável por lote e por item.

## Recursos

- Produtos e Serviços, Domínios e Addons.
- Seleção individual e em massa na página atual.
- Filtros específicos para cada tipo de registro.
- Sincronização com o preço atual do catálogo do WHMCS.
- Aumento ou redução por percentual.
- Aumento ou redução por valor fixo.
- Definição de novo valor absoluto.
- Simulação obrigatória antes de qualquer alteração.
- Revalidação dos preços no momento da confirmação.
- Histórico de lotes e itens.
- Reversão protegida contra alterações posteriores.
- Processamento individual por item.
- Preservação integral das faturas existentes.
- Auditoria administrativa e impacto financeiro por lote.
- Interface administrativa integrada ao padrão visual do WHMCS.

## Compatibilidade

- WHMCS 9.0.5 ou superior da linha 9.x
- PHP 8.3, 8.4 e 8.5, respeitando a matriz da versão do WHMCS instalada
- MySQL ou MariaDB suportado pelo WHMCS
- Tema administrativo Blend

O módulo utiliza o Capsule já carregado pelo WHMCS e não exige Composer, bibliotecas externas ou conexão com serviços de terceiros.

Consulte [docs/COMPATIBILIDADE.md](docs/COMPATIBILIDADE.md) para mais detalhes.

## Instalação

1. Baixe o pacote instalável da Release correspondente.
2. Extraia o conteúdo do ZIP.
3. Envie a pasta `modules` para a raiz da instalação do WHMCS, mesclando-a com a estrutura existente.
4. Acesse **Configuração do Sistema > Módulos Addon**.
5. Ative **Alphavision WHMCS Bulk Pricing Manager**.
6. Defina quais funções administrativas podem acessar o addon.
7. Abra **Addons > Preços em Massa**.

A pasta operacional do módulo é:

```text
modules/addons/alphavision_bulk_pricing_manager/
```

## Fluxo de uso

1. Escolha Produtos e Serviços, Domínios ou Addons.
2. Aplique os filtros desejados.
3. Selecione os registros que serão alterados.
4. Escolha a operação de preço.
5. Gere a simulação.
6. Revise os valores Antes e Depois.
7. Confirme a execução.
8. Consulte o resultado individual no Histórico.

## Operações disponíveis

- Sincronizar com o preço atual do catálogo
- Aumentar por percentual
- Reduzir por percentual
- Aumentar por valor fixo
- Reduzir por valor fixo
- Definir novo valor absoluto

## Segurança

O módulo foi projetado para reduzir o risco de alterações financeiras acidentais.

Entre os controles implementados estão:

- acesso restrito ao painel administrativo;
- Access Control nativo do WHMCS;
- token CSRF vinculado à sessão administrativa;
- simulação obrigatória;
- revalidação antes da gravação;
- proteção contra reutilização da simulação;
- transação individual por item;
- auditoria de administrador, valores e resultado;
- reversão condicionada ao estado atual do registro;
- ausência de rotinas que alterem faturas existentes.

Não publique vulnerabilidades em Issues públicas. Consulte [SECURITY.md](SECURITY.md).

## Regras de sincronização com catálogo

Produtos e addons utilizam a matriz de `tblpricing` correspondente à moeda e ao ciclo contratado.

Para serviços, o cálculo considera quantidade e opções configuráveis quando aplicáveis. Serviços com promoção ativa não são sincronizados automaticamente.

Domínios utilizam o preço de renovação correspondente à moeda, TLD e período. A sincronização automática é bloqueada para domínios premium, domínios com promoção e domínios com addons que componham a cobrança.

Valores de catálogo negativos, ciclos gratuitos, ciclos personalizados ou combinações inexistentes são tratados como indisponíveis para sincronização automática.

## Reversão

A reversão cria um novo lote e preserva o lote original.

Um item não será revertido quando:

- tiver sofrido outra alteração registrada depois do lote original;
- o preço atual não corresponder ao valor deixado pelo lote original;
- o registro não existir mais.

## Banco de dados

Na ativação, o addon cria:

```text
mod_av_bulkpricing_batches
mod_av_bulkpricing_items
```

A desativação preserva essas tabelas para manter o histórico de auditoria.

## Documentação

- [CHANGELOG.md](CHANGELOG.md): histórico de versões.
- [docs/COMPATIBILIDADE.md](docs/COMPATIBILIDADE.md): matriz de compatibilidade.
- [docs/HOMOLOGACAO.md](docs/HOMOLOGACAO.md): roteiro de homologação.
- [docs/REMOCAO.md](docs/REMOCAO.md): remoção definitiva.
- [docs/SEGURANCA.md](docs/SEGURANCA.md): práticas operacionais de segurança.
- [docs/audits/](docs/audits/): relatórios de auditoria preservados.
- [docs/releases/](docs/releases/): notas das versões publicadas.

## Contribuindo

Contribuições são bem-vindas.

Você pode estudar, modificar e propor melhorias por meio de Issues e Pull Requests. Antes de contribuir, consulte as diretrizes de contribuição e o Código de Conduta disponibilizados pela organização Alphavision® no GitHub.

## Suporte

Consulte [SUPPORT.md](SUPPORT.md) antes de abrir uma solicitação.

## Licença

Este projeto é distribuído sob a **MIT License**.

Identificador SPDX:

```text
MIT
```

Consulte o arquivo [LICENSE](LICENSE).

## Alphavision®

Desenvolvido e mantido pela **Alphavision®**.

**Projeto:** https://github.com/alphavisionbr/whmcs-bulk-pricing-manager  
**Site:** https://alphavision.com.br  
**Contato:** contato@alphavision.com.br

---

**Alphavision®**  
Web Platforms, Cloud Infrastructure & Digital Solutions
