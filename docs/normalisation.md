# Système de normalisation des noms

## Pourquoi normaliser ?

Lors de l'auto-mapping, MSP Consolidator compare les noms des clients fournisseurs aux noms de vos clients internes par similarité textuelle. Ce mécanisme échoue ou produit des correspondances incorrectes quand les noms divergent à cause de suffixes administratifs ou de conventions de nommage propres à chaque fournisseur.

**Exemples typiques :**

| Nom dans le fournisseur | Nom client interne | Problème |
|---|---|---|
| `Dupont SA` | `Dupont` | Le suffixe `SA` fausse la comparaison |
| `Dupont - Agence Lyon` | `Dupont` | Le suffixe ` - Agence Lyon` fausse la comparaison |
| `DUPONT SARL` | `Dupont` | Forme juridique + casse différente |

Après normalisation, les trois noms fournisseurs deviennent `dupont`, ce qui permet une correspondance correcte avec `Dupont`.

---

## Types de règles

### 1. Formes juridiques (automatique)

MSP Consolidator retire automatiquement les formes juridiques françaises et belges courantes :

`SA`, `SAS`, `SARL`, `SASU`, `SNC`, `SC`, `SCI`, `SCOP`, `GIE`, `EIRL`, `EURL`, `SRL`, `BV`, `NV`, `SPRL`, `ASBL`, `VZW`

Ces formes sont supprimées en tant que **mots entiers** (insensible à la casse), ce qui évite de supprimer `SAS` à l'intérieur d'un nom comme `OASIS`.

> Ces règles sont **pré-configurées** et ne nécessitent pas d'intervention manuelle. Elles peuvent être activées/désactivées individuellement depuis **Paramètres → Normalisation**.

### 2. Exclusions personnalisées

Vous pouvez ajouter vos propres règles de suppression : toute sous-chaîne que vous définissez sera retirée du nom avant comparaison.

**Exemples d'exclusions à configurer selon votre contexte :**

| Valeur à exclure | Cas d'usage |
|---|---|
| ` - Agence` | Vos clients ont ` - Agence` dans leur nom fournisseur |
| ` - Groupe` | Nom de groupe ajouté dans la console |
| ` (test)` | Comptes de test créés dans la console fournisseur |
| `MSP-` | Préfixe interne ajouté dans une console |

---

## Comment configurer

1. Allez dans **Paramètres → Normalisation**
2. Section *Exclusions personnalisées* → cliquez **Ajouter**
3. Saisissez la sous-chaîne exacte à supprimer (respectez les espaces et tirets)
4. Enregistrez

La règle est active immédiatement pour les prochaines opérations de mapping (pas besoin de relancer une sync).

> **Astuce :** si un auto-mapping échoue pour un client dont le nom est très proche, vérifiez d'abord si un suffixe récurrent dans votre console fournisseur n'est pas à la source du problème, avant de créer un mapping manuel.

---

## Processus de normalisation (détail technique)

La normalisation est appliquée par la classe `app/Core/NameNormalizer.php` selon les étapes suivantes :

1. Conversion en minuscules
2. Suppression des formes juridiques actives (mot entier, insensible à la casse)
3. Suppression des exclusions personnalisées actives (sous-chaîne exacte, insensible à la casse)
4. Suppression des caractères spéciaux courants (`.`, `,`, `'`, `"`)
5. Normalisation des espaces multiples
6. Trim

Le résultat normalisé est ensuite comparé avec l'algorithme de similarité `similar_text()` / distance de Levenshtein. Un score minimum configurable (par défaut 80%) déclenche une suggestion de mapping.

---

## Impact sur le mapping existant

Modifier ou ajouter des règles de normalisation **n'affecte pas les mappings déjà confirmés**. Les nouvelles règles s'appliquent uniquement :
- Lors des prochaines synchronisations (nouveaux clients fournisseurs)
- Lors d'une recherche manuelle sur la page Mapping

Pour recalculer les suggestions sur des clients déjà connus, utilisez le bouton **Recalculer le mapping auto** disponible sur la page Mapping.
