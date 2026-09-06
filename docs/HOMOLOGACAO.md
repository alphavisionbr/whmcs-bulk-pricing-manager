# Roteiro de homologação

## Preparação

1. use uma cópia recente do banco de produção;
2. confirme que o ambiente não envia cobranças ou e-mails reais;
3. gere um backup antes da ativação;
4. registre os valores originais dos casos escolhidos;
5. conceda acesso somente ao grupo administrativo de testes.

## Instalação

1. envie o conteúdo da pasta de release para a raiz do WHMCS;
2. confirme a existência de `modules/addons/alphavision_bulk_pricing_manager`;
3. ative o addon;
4. salve as configurações;
5. confirme que o menu exibe `Preços em Massa`;
6. confirme que a gestão de addons exibe o nome oficial completo.

## Casos obrigatórios

1. selecione oito serviços entre dez e confirme que apenas oito foram alterados;
2. simule um serviço em cada ciclo utilizado no ambiente;
3. teste serviço com quantidade maior que um;
4. teste serviço com opção configurável;
5. confirme que serviço com promoção não sincroniza automaticamente;
6. teste domínio de um ano e domínio de vários anos;
7. confirme que domínio premium ou com addon não sincroniza automaticamente;
8. teste um addon recorrente;
9. teste cada moeda ativa;
10. altere um preço depois da simulação e antes da confirmação;
11. confirme que o item concorrente foi ignorado;
12. execute uma reversão normal;
13. altere novamente um item e confirme o bloqueio da reversão;
14. confira os registros do lote e de cada item;
15. confirme que faturas abertas, pagas e canceladas não mudaram.
16. confirme que o menu exibe somente `Preços em Massa`, inclusive após limpar o cache do navegador;
17. confira se serviço, domínio e addon mostram o ID junto ao próprio registro;
18. abra um lote antigo e confirme a recuperação dos dados atuais disponíveis;
19. crie um novo lote e confirme nome do registro, cliente, ciclo, status e valores no histórico;
20. confira ação, parâmetro, filtros, totais e diferença na página do lote.
21. abra cada aba administrativa e confirme a presença do bloco `Projeto gratuito, Apoie o desenvolvimento` após o conteúdo;
22. confirme que `Página do projeto` abre em uma nova aba e `Reportar um problema` abre o cliente de e-mail;
23. confirme que `Fazer uma contribuição` permanece visível e desabilitado.

## Critério de aprovação

A versão só pode ser promovida para produção quando todos os casos aplicáveis forem aprovados e as divergências estiverem documentadas.
