1. Cream un post type nou "Directory"
2. Cream un taxonomy directory_cat (categorii)
3. Cream un taxonomy directory_tag (tag)
4. Voi avea nevoie de un formular pe frontend pentru a completa informatia despre acest directori:
1. Provider Name (text)
2. Provider Avatar ( this need to be cropped 150px width on import)
2. Choose Category (One)
3. Chose Services (tags multiple)
4. Short description (textarea limit 120chars)
5. Full Description (Textarea limit 500chars)
6. Serives List (Repeater with text input) that will save into array of texts
7. Location ( i want to be used input text where you write addres and google hint the place, when save this input in DB we have "adress" like text , also " "latlong" coordinates.
8. Contact Information ( Phone, Email, Facebook, Twiter, Pinterest, linked in, Whatsap, instagram)

Ps.
Cind se salveaza directory trebuie sa salvam undeva si author ID (persoana care a creat postul), doar authorID va avea access la editare si (Admin role)
