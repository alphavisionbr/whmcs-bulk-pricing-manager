# Segurança

O Alphavision® WHMCS Bulk Pricing Manager altera dados financeiros recorrentes e deve ser utilizado com os mesmos cuidados aplicados à administração do WHMCS.

## Controles implementados

- acesso restrito ao painel administrativo;
- Access Control nativo do WHMCS;
- token CSRF vinculado à sessão administrativa;
- simulação obrigatória;
- revalidação antes da gravação;
- proteção contra reutilização da simulação;
- consultas por Capsule e parâmetros validados;
- transação individual por item;
- auditoria do administrador, valores e resultado;
- reversão condicionada ao estado atual do registro;
- ausência de rotinas que alterem faturas existentes.

## Relato responsável de vulnerabilidades

Não publique vulnerabilidades de segurança em Issues públicas.

Ao comunicar um possível problema, inclua, quando possível:

- versão do módulo;
- versão do WHMCS;
- versão do PHP;
- passos para reprodução;
- impacto observado;
- evidências técnicas sem dados pessoais, credenciais ou informações de clientes.

Envie o relato para:

**contato@alphavision.com.br**

A Alphavision® analisará o relato e poderá solicitar informações adicionais antes de confirmar, corrigir e divulgar o problema.
