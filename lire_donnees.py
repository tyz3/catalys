import pandas as pd
import sqlite3
import os

fichiers_source = {
    'seance_conseiller.xlsx': 'données/seance_conseiller.xlsx',
    'dt2024_rdv_202505190828.xlsx': 'données/dt_rdv_20250616.xlsx'
}

chemin_base_sqlite = 'données/agenda.sqlite'
os.makedirs(os.path.dirname(chemin_base_sqlite), exist_ok=True)

with sqlite3.connect(chemin_base_sqlite) as connexion_sqlite:
    for nom_fichier, chemin_fichier in fichiers_source.items():
        if not os.path.exists(chemin_fichier):
            print(f"❌ Fichier introuvable : {chemin_fichier}")
            continue
        try:
            dataframe = pd.read_excel(chemin_fichier)
            nom_table_sqlite = 'seances' if 'seance' in nom_fichier.lower() else 'rdv'
            dataframe = dataframe.loc[:, ~dataframe.columns.str.contains('^Unnamed')]
            dataframe.to_sql(nom_table_sqlite, connexion_sqlite, if_exists='replace', index=False)
            print(f"✅ Table '{nom_table_sqlite}' créée ({len(dataframe)} lignes)")
        except Exception as erreur:
            print(f"❌ Erreur pour {nom_fichier} : {erreur}")

print(f"\n🎉 Base SQLite générée : {chemin_base_sqlite}")
