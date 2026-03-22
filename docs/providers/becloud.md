# Configuration — Be-Cloud / Microsoft 365

> Cette page sera complétée avec les détails de configuration spécifiques à Be-Cloud.

## Informations requises

- `tenant_id` : ID tenant Microsoft Entra (valeur publique Be-Cloud, pré-remplie)
- `scope` : Scope OAuth2 (valeur publique Be-Cloud, pré-remplie)
- `client_id` : ID de votre application revendeur dans Entra
- `client_secret` : Secret de votre application revendeur
- `csp_url` : Identifiant tenant CloudCockpit (valeur X-Tenant, ex : `csp.lti.eu`)
- `base_url` : URL de base de l'API CloudCockpit

## Données synchronisées

- Clients Be-Cloud (be_cloud_customers)
- Abonnements Microsoft (be_cloud_subscriptions) : offre, statut, quantité, dates, cycle de facturation, prix
- Licences M365 (be_cloud_licenses) : consommation réelle par SKU (total / consommées / disponibles / suspendues)

---

*Documentation à compléter.*
