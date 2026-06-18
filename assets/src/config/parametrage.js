// Définition déclarative des 4 entités de paramétrage. Le champ `name` est
// implicite (toujours présent). Les CRUD génériques (ParametrageList /
// ParametrageForm) se configurent à partir de cet objet, indexé par le segment
// d'URL (= endpoint API).
export const RESOURCES = {
    types: {
        endpoint: '/types',
        title: 'Types',
        singular: 'Type',
        // colonnes additionnelles affichées dans la liste (au-delà du nom)
        columns: [{ label: 'Parent', value: (r) => r.parent?.name ?? '—' }],
        // champs additionnels du formulaire (au-delà du nom)
        fields: [{ name: 'parent', label: 'Parent', kind: 'select', optionsEndpoint: '/types', required: false }],
    },
    'porte-greffes': {
        endpoint: '/porte-greffes',
        title: 'Porte-greffes',
        singular: 'Porte-greffe',
        columns: [{ label: 'Type', value: (r) => r.type?.name ?? '—' }],
        fields: [{ name: 'type', label: 'Type', kind: 'select', optionsEndpoint: '/types', required: true }],
    },
    groups: {
        endpoint: '/groups',
        title: 'Groupes',
        singular: 'Groupe',
        columns: [{ label: 'Parent', value: (r) => r.parent?.name ?? '—' }],
        fields: [{ name: 'parent', label: 'Parent', kind: 'select', optionsEndpoint: '/groups', required: false }],
    },
    lieux: {
        endpoint: '/lieux',
        title: 'Lieux',
        singular: 'Lieu',
        columns: [],
        fields: [],
    },
};

export function resourceOr404(key) {
    return RESOURCES[key] ?? null;
}
