"""Generate _hiden/demo-listings.json: 100 fake Directory listings across US cities."""
import json, random, sys

random.seed(42)
cats = json.load(open(sys.argv[1]))
out = sys.argv[2]

# (city, state, zip prefix, lat, lng, how many)
CITIES = [
    ("New York", "NY", "100", 40.7484, -73.9857, 14),
    ("Washington", "DC", "200", 38.9072, -77.0369, 12),
    ("Los Angeles", "CA", "900", 34.0522, -118.2437, 12),
    ("Chicago", "IL", "606", 41.8781, -87.6298, 6),
    ("Houston", "TX", "770", 29.7604, -95.3698, 5),
    ("Miami", "FL", "331", 25.7617, -80.1918, 5),
    ("Wellington", "FL", "334", 26.6587, -80.2414, 8),
    ("Ocala", "FL", "344", 29.1872, -82.1401, 6),
    ("Lexington", "KY", "405", 38.0406, -84.5037, 7),
    ("Dallas", "TX", "752", 32.7767, -96.7970, 4),
    ("Atlanta", "GA", "303", 33.7490, -84.3880, 4),
    ("Denver", "CO", "802", 39.7392, -104.9903, 4),
    ("Boston", "MA", "021", 42.3601, -71.0589, 4),
    ("Philadelphia", "PA", "191", 39.9526, -75.1652, 3),
    ("San Diego", "CA", "921", 32.7157, -117.1611, 3),
    ("Seattle", "WA", "981", 47.6062, -122.3321, 3),
]
assert sum(c[5] for c in CITIES) == 100

AREA = {"New York": "212", "Washington": "202", "Los Angeles": "213", "Chicago": "312",
        "Houston": "713", "Miami": "305", "Wellington": "561", "Ocala": "352", "Lexington": "859",
        "Dallas": "214", "Atlanta": "404", "Denver": "303", "Boston": "617", "Philadelphia": "215",
        "San Diego": "619", "Seattle": "206"}
STREETS = ["Main St", "Oak Ave", "Maple Dr", "Park Ave", "Cedar Ln", "Elm St", "Washington Blvd",
           "Lake Rd", "Hillside Ave", "Meadow Ln", "Ridge Rd", "Sunset Blvd", "Broadway", "Mill Rd",
           "Church St", "River Rd", "Highland Ave", "Forest Dr", "Spring St", "Stable Way"]
FIRST = ["Silver", "Golden", "Blue Ridge", "Willow", "Oak Hill", "Maple Leaf", "Red Barn", "Stone Bridge",
         "Clover", "Meadowbrook", "Sunrise", "Pine Valley", "Whitehorse", "Iron Gate", "Harbor", "Liberty",
         "Northstar", "Fox Run", "Bluegrass", "Cedar Creek", "Twin Oaks", "Wildflower", "Highfield", "Riverbend"]
SUFFIX = ["Equine", "Stables", "Equestrian", "Farm", "Horse Co.", "& Co.", "Group", "Services", "Studio", "Partners"]

# Shared "Services" tags (directory_tag).
TAGS = ["Mobile Service", "Emergency 24/7", "Barn Calls", "Online Booking", "Free Consultation",
        "Show Season Support", "Insured & Licensed", "Bilingual (EN/ES)", "Weekend Availability",
        "Payment Plans", "Group Discounts", "Certified Professional"]

LINES = ["Free initial consultation", "Same-week appointments", "Written report after every visit",
         "Travel within 50 miles", "Show-day support", "Follow-up call included", "Flexible scheduling",
         "Package pricing available", "Digital records & photos", "Emergency call-outs",
         "Experienced with young horses", "Works with your vet and farrier"]

TAGLINES = [
    "Trusted {cat} serving {city} and surrounding barns.",
    "Friendly, reliable {cat} for riders and horses in {city}.",
    "{city}'s go-to {cat} for show and pleasure horses.",
    "Professional {cat} with over {yrs} years of experience.",
    "Personal, horse-first {cat} in the {city} area.",
]
FULL = [
    "{name} has worked with horses and riders around {city} for more than {yrs} years. "
    "We focus on clear communication, fair pricing and doing the job right the first time. "
    "Whether you keep one horse at home or manage a busy show barn, we will fit around your schedule.",
    "At {name} every horse is treated like our own. Our team offers {cat_l} services for "
    "pleasure riders, amateurs and professionals across the {city} area. "
    "Call or message us to talk about what your horse needs and we will put together a simple plan.",
    "Founded in {year}, {name} is a {cat_l} business built on experience and repeat clients. "
    "We travel to barns throughout {city} and nearby counties and are happy to coordinate with "
    "your trainer, vet and barn manager.",
]

children = [(parent, child) for parent, kids in cats.items() for child in kids]
used_names = set()
rows = []
n = 0
for city, st, zp, lat, lng, count in CITIES:
    for _ in range(count):
        n += 1
        parent, cat = random.choice(children)
        while True:
            name = f"{random.choice(FIRST)} {random.choice(SUFFIX)}"
            if name not in used_names:
                used_names.add(name); break
        yrs = random.randint(4, 30)
        street = f"{random.randint(10, 9800)} {random.choice(STREETS)}"
        zipc = zp + f"{random.randint(1, 99):02d}"
        address = f"{street}, {city}, {st} {zipc}, USA"
        plat = round(lat + random.uniform(-0.07, 0.07), 6)
        plng = round(lng + random.uniform(-0.09, 0.09), 6)
        slug = "".join(ch for ch in name.lower().replace("&", "and") if ch.isalnum())[:24]
        short = random.choice(TAGLINES).format(cat=cat.lower(), city=city, yrs=yrs)[:120]
        full = random.choice(FULL).format(name=name, city=city, yrs=yrs, cat_l=cat.lower(),
                                          year=2026 - yrs)[:500]
        rows.append({
            "demo_id": f"demo-{n:03d}",
            "provider_name": name,
            "category": cat,
            "category_parent": parent,
            "services": random.sample(TAGS, random.randint(2, 4)),
            "short_description": short,
            "full_description": full,
            "services_list": random.sample(LINES, random.randint(3, 5)),
            "location": {
                "address": address,
                "city": f"{city}, {st}",
                "lat": plat,
                "lng": plng,
            },
            "contact": {
                # 555-01xx numbers are reserved for fiction.
                "phone": f"({AREA[city]}) 555-01{random.randint(0, 99):02d}",
                "email": f"hello@{slug}.example.com",
            },
            "date": f"2026-{random.randint(1, 9):02d}-{random.randint(1, 28):02d} {random.randint(8, 18):02d}:{random.randint(0, 59):02d}:00",
        })

json.dump({"version": 1, "tags": TAGS, "listings": rows}, open(out, "w"), indent=2, ensure_ascii=False)
print(len(rows), "listings ->", out)
