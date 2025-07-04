import pandas as pd
import sqlite3
import os

# fichiers Excel source à importer
fichiers_source = {
    'seance_conseiller.xlsx': 'données/seance_conseiller.xlsx',
    'dt2024_rdv_202505190828.xlsx': 'données/dt_rdv_20250616.xlsx'
}

# chemin de la base SQLite à générer
chemin_base_sqlite = 'données/agenda.sqlite'


# Connexion à la base SQLite (créée si elle n'existe pas)
with sqlite3.connect(chemin_base_sqlite) as connexion_sqlite:
    for nom_fichier, chemin_fichier in fichiers_source.items():
        if not os.path.exists(chemin_fichier):
            print(f"Fichier introuvable : {chemin_fichier}")
            continue

        try:
            # lecture fichier Excel dans un dataframe 
            dataframe = pd.read_excel(chemin_fichier)
            
            nom_table_sqlite = 'seances' if 'seance' in nom_fichier.lower() else 'rdv' 
            dataframe.to_sql(nom_table_sqlite, connexion_sqlite, if_exists='replace', index=False)
            print(f"Table '{nom_table_sqlite}' créée avec {len(dataframe)} lignes")
        except Exception as erreur:
            print(f"Erreur lors du traitement de {nom_fichier} : {erreur}")


print(f"Base SQLite générée : {chemin_base_sqlite}")
