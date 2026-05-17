import re
from pathlib import Path

p = Path(__file__).resolve().parents[1] / "html" / "admin" / "review-reports.html"
t = p.read_text(encoding="utf-8")

t = t.replace(
    "loadEvacuationChoices(r.barangay_id, r.suggested_evacuation_center_id);",
    "await loadEvacuationChoices(r.barangay_id, r.suggested_evacuation_center_id, r.map_latitude, r.map_longitude);",
)

t = re.sub(
    r"if \(typeof L !== 'undefined' && r\.map_latitude && r\.map_longitude\) \{\s*"
    r"setTimeout\(\(\) => \{\s*const el = document\.getElementById\('reportDetailMap'\);.*?"
    r"detailMap\.invalidateSize\(\);\s*\}, 100\);\s*\}",
    "if (typeof L !== 'undefined' && r.map_latitude && r.map_longitude) {\n"
    "                    setTimeout(() => initReportDetailMap(r), 120);\n"
    "                }",
    t,
    count=1,
    flags=re.DOTALL,
)

t = t.replace(
    "Sorted by road distance from the report pin",
    "Sorted by distance from the report pin (straight-line)",
)
t = t.replace("<motion-div", "<motion-div").replace("motion-div", "div") if "motion-div" in t else t
if "motion-div" in t:
    t = t.replace("motion-div", "div")

p.write_text(t, encoding="utf-8")
print("done")
