// Aplatit une liste de types de graines (plats, avec parentId) en ordre
// hiérarchique parent → enfants, en annotant chaque entrée de sa profondeur.
// Les types par utilisateur sont peu nombreux ; tri par nom à chaque niveau.

export function flattenGraineTypes(types) {
    const childrenByParent = new Map();
    for (const t of types) {
        const key = t.parentId ?? null;
        if (!childrenByParent.has(key)) childrenByParent.set(key, []);
        childrenByParent.get(key).push(t);
    }
    for (const list of childrenByParent.values()) {
        list.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));
    }

    const out = [];
    const walk = (parentId, depth) => {
        for (const t of childrenByParent.get(parentId) ?? []) {
            out.push({ ...t, depth });
            walk(t.id, depth + 1);
        }
    };
    walk(null, 0);

    // Sécurité : types orphelins (parent introuvable) rattachés à la racine.
    if (out.length < types.length) {
        const seen = new Set(out.map((t) => t.id));
        for (const t of types) {
            if (!seen.has(t.id)) out.push({ ...t, depth: 0 });
        }
    }
    return out;
}

// Préfixe d'indentation pour une <option> (« — » répété selon la profondeur).
export function indentLabel(item) {
    return '— '.repeat(item.depth) + item.name;
}
