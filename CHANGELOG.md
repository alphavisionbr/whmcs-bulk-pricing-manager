# Changelog

Todas as alterações relevantes do Alphavision® WHMCS Bulk Pricing Manager são documentadas neste arquivo.

## Distribuição pública

A versão **1.0.2** é a primeira versão disponibilizada publicamente no GitHub. O código funcional corresponde à versão 1.0.2 testada e homologada, com alteração de licenciamento para **MIT License** e organização da documentação para o repositório público.

Nenhuma alteração funcional foi introduzida exclusivamente pela mudança de licenciamento.

## 1.0.2 | 2026-08-03

* Adicionado o bloco `Projeto gratuito, Apoie o desenvolvimento` em todas as telas administrativas.
* Incluídos os acessos para página do projeto e relato de problema.
* Mantido o botão de contribuição desabilitado até a disponibilização do canal oficial.
* Adicionada adaptação responsiva do bloco para telas menores.

## 1.0.1 | 2026-08-03

* Adicionado fallback direcionado para exibir `Preços em Massa` no menu Addons.
* Removida a coluna isolada de ID das listas e simulações.
* ID contextualizado como serviço, domínio, addon ou cliente.
* Histórico reorganizado por ação, escopo e impacto financeiro.
* Adicionados totais antes, depois e diferença na listagem de lotes.
* Adicionados parâmetros, filtros, administrador e período no detalhe do lote.
* Auditoria passa a armazenar nome do registro, cliente e contexto histórico.
* Migração incremental preserva lotes criados pela versão 1.0.0.
* Registros antigos tentam recuperar seus nomes a partir dos dados atuais do WHMCS.

## 1.0.0 | 2026-08-03

* Primeira versão funcional.
* Seleção individual e em massa na página atual.
* Filtros específicos para serviços, domínios e addons.
* Seis operações de atualização de preço.
* Simulação obrigatória com totais e resultado por item.
* Revalidação de preço contratado e catálogo na confirmação.
* Auditoria por lote e por item.
* Reversão protegida contra alterações posteriores.
* Processamento em blocos configuráveis.
* Preservação integral de faturas existentes.
* Cabeçalho e navegação no padrão administrativo Alphavision.
* Nome curto `Preços em Massa` apenas no menu Addons.
* Pacote de release com estrutura `modules/addons` pronta para envio à raiz do WHMCS.
* Documentação e arquivos de release mantidos fora da pasta operacional do addon.
