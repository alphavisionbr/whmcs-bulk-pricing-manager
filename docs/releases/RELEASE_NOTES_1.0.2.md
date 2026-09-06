# Alphavision WHMCS Bulk Pricing Manager v1.0.2

Versão corretiva da primeira etapa de homologação.

## Correções da versão

1. bloco `Projeto gratuito, Apoie o desenvolvimento` presente em todas as telas administrativas;
2. ações para abrir a página do projeto e reportar um problema;
3. botão de contribuição preparado e desabilitado até a definição do canal oficial;
4. nome curto do menu reforçado com fallback direcionado;
5. IDs integrados ao contexto de cada registro;
6. histórico com ação, escopo, impacto, sucessos e itens ignorados;
7. detalhe do lote com filtros, administrador, período e valores totais;
8. migração incremental das tabelas criadas pela versão 1.0.0.

## Recursos principais

1. seleção individual de serviços, domínios e addons;
2. filtros específicos para cada tipo de registro;
3. sincronização com o catálogo do WHMCS;
4. reajuste percentual, fixo e por valor absoluto;
5. simulação obrigatória antes de qualquer alteração;
6. revalidação de preço na confirmação;
7. histórico de lotes e itens;
8. reversão protegida contra alterações posteriores;
9. preservação integral das faturas existentes;
10. interface administrativa no padrão Alphavision WHMCS.

## Proteções adicionais

* preços compostos consideram quantidade e opções configuráveis;
* promoções ativas bloqueiam a sincronização automática;
* domínios premium ou com addons ativos não são sincronizados automaticamente;
* uma falha individual não oculta o resultado dos demais registros;
* a simulação é consumida na primeira tentativa de execução.

## Compatibilidade declarada

* WHMCS 9.0.5 ou superior da linha 9.x;
* PHP 8.3, 8.4 e 8.5, respeitando a matriz da versão instalada do WHMCS;
* MySQL ou MariaDB suportado pelo WHMCS;
* painel administrativo Blend.
