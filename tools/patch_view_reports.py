from pathlib import Path

path = Path(__file__).resolve().parents[1] / "html" / "user" / "view-reports.html"
text = path.read_text(encoding="utf-8")

if "../js/relief-ajax.js" not in text:
    text = text.replace(
        '<script src="../js/relief-sidebar.js"></script>',
        '<script src="../js/relief-ajax.js"></script>\n    <script src="../js/report-structured.js"></script>\n    <script src="../js/relief-sidebar.js"></script>',
        1,
    )

old_card = """                <motion-div class="card" style="max-width: 600px; width: 100%; margin: 0; position:relative; max-height:90vh; overflow-y:auto; background:#fff; border-radius:12px; padding:24px;">
                    <motion-div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:12px;">
                        <h3 style="margin:0; font-size:18px;">Incident Report Details</h3>
                        <button type="button" onclick="closeDetailModal()" style="border:none; background:none; cursor:pointer; font-size:24px; line-height:1; color:var(--text-subtle);">&times;</button>
                    </motion-div>
                    <motion-div id="detailModalContent"></motion-div>
                </motion-div>""".replace("motion-div", "div")

new_card = """                <div class="card" style="max-width: 640px; width: 100%; margin: 0; max-height: calc(100vh - 48px); display:flex; flex-direction:column; background:#fff; border-radius:12px; padding:24px; box-sizing:border-box; overflow:hidden;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:12px; flex-shrink:0;">
                        <h3 style="margin:0; font-size:18px;">Incident Report Details</h3>
                        <button type="button" onclick="closeDetailModal()" style="border:none; background:none; cursor:pointer; font-size:24px; line-height:1; color:var(--text-subtle);">&times;</button>
                    </div>
                    <div id="detailModalContent" style="flex:1; overflow-y:auto; padding-right:4px;"></div>
                </div>"""

if old_card in text:
    text = text.replace(old_card, new_card)

pod_modal = """
            <div id="podModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center; padding:24px;">
                <div style="background:#fff;border-radius:12px;max-width:480px;width:100%;padding:24px;">
                    <h3 style="margin-bottom:8px;">Confirm relief received</h3>
                    <p style="font-size:13px;color:var(--text-subtle);margin-bottom:16px;">Upload a delivery receipt photo and/or sign below when aid reaches your barangay hall.</p>
                    <input type="hidden" id="podReportId">
                    <div class="form-group">
                        <label class="form-label">Delivery photo</label>
                        <input type="file" id="podPhoto" accept="image/*" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Digital signature</label>
                        <canvas id="podSignature" width="400" height="140" style="width:100%;border:1px solid var(--border);border-radius:6px;touch-action:none;background:#fafbfc;"></canvas>
                        <button type="button" class="btn btn-sm" style="margin-top:8px;border:1px solid var(--border);" id="podClearSig">Clear signature</button>
                    </div>
                    <motion-div style="display:flex;gap:10px;margin-top:16px;">
                        <button type="button" class="btn btn-primary" id="podSubmitBtn">Submit proof</button>
                        <button type="button" class="btn" style="border:1px solid var(--border);" id="podCancelBtn">Cancel</button>
                    </motion-div>
                </motion-div>
            </motion-div>
""".replace("motion-div", "motion-div").replace("motion-div", "div")

if 'id="podModal"' not in text:
    anchor = "            </div>\n        </main>\n    </div>\n\n    <script src="
    if anchor in text:
        text = text.replace(anchor, "            </div>\n" + pod_modal + "        </main>\n    </div>\n\n    <script src=", 1)

start = text.find("        function renderDescriptionField(rawText) {")
end = text.find("        function closeDetailModal()", start)
if start > 0 and end > start:
    text = (
        text[:start]
        + """        function renderDescriptionField(rawText) {
            return ReliefStructuredReport.renderHtml(ReliefStructuredReport.parseDescription(rawText || ''), esc);
        }

"""
        + text[end:]
    )

path.write_text(text, encoding="utf-8")
print("patched", path)
