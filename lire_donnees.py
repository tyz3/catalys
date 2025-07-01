import pandas as pd
import sqlite3
import os
import chardet

fichiers_source = {
    'seance_conseiller.xlsx': 'données/seance_conseiller.xlsx',
    'dt2024_rdv_202505190828.csv': 'données/dt2024_rdv_202505190828.csv'
}

chemin_base_sqlite = 'données/agenda.sqlite'
os.makedirs(os.path.dirname(chemin_base_sqlite), exist_ok=True)

def detecter_separateur_et_encodage(fichier_csv):
    with open(fichier_csv, 'rb') as fichier_bin:
        separateur = '//'
        encodage = chardet.detect(fichier_bin.read(2048))['encoding']
    return separateur, encodage

def lire_fichier_csv(fichier_csv, separateur, liste_encodages):
    for enc in liste_encodages:
        try:
            return pd.read_csv(fichier_csv, sep=separateur, encoding=enc, on_bad_lines='skip')
        except Exception:
            pass
    raise Exception("Lecture impossible avec les encodages testés")

with sqlite3.connect(chemin_base_sqlite) as connexion_sqlite:
    for nom_fichier, chemin_fichier in fichiers_source.items():
        if not os.path.exists(chemin_fichier):
            print(f"❌ Fichier introuvable : {chemin_fichier}")
            continue
        extension_fichier = os.path.splitext(chemin_fichier)[1].lower();
        try:
            if extension_fichier == '.xlsx':
                dataframe = pd.read_excel(chemin_fichier)
                nom_table_sqlite = 'seances'
            else:
                separateur_detecte, encodage_detecte = detecter_separateur_et_encodage(chemin_fichier)
                dataframe = lire_fichier_csv(chemin_fichier, separateur_detecte, [encodage_detecte, 'latin1', 'iso-8859-1', 'cp1252', 'utf-8'])
                nom_table_sqlite = 'rdv'

            dataframe = dataframe.loc[:, ~dataframe.columns.str.contains('^Unnamed')]
            dataframe.to_sql(nom_table_sqlite, connexion_sqlite, if_exists='replace', index=False)
            print(f"✅ Table '{nom_table_sqlite}' créée ({len(dataframe)} lignes)")
        except Exception as erreur:
            print(f"❌ Erreur pour {nom_fichier} : {erreur}")

print(f"\n🎉 Base SQLite générée : {chemin_base_sqlite}")