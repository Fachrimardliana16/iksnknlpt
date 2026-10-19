import json, subprocess, tempfile, time, urllib.request, shutil, sys
import websocket

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
PORT = 9451
URL = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8100/produk/knalpot-full-system-nmax-v2-titanium"

profil = tempfile.mkdtemp(prefix="chrome-dbg3-")
p = subprocess.Popen([CHROME, "--headless=new", f"--remote-debugging-port={PORT}",
                      f"--user-data-dir={profil}", "--no-first-run", "--disable-gpu",
                      "--remote-allow-origins=*", "--window-size=375,667", "about:blank"],
                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
try:
    for _ in range(100):
        try:
            urllib.request.urlopen(f"http://127.0.0.1:{PORT}/json/version", timeout=1); break
        except Exception: time.sleep(0.25)
    with urllib.request.urlopen(f"http://127.0.0.1:{PORT}/json/list", timeout=5) as r:
        t = next(x for x in json.load(r) if x["type"] == "page")
    ws = websocket.create_connection(t["webSocketDebuggerUrl"], timeout=30, suppress_origin=True)
    n = 0
    def cmd(m, **params):
        global n
        n += 1
        ws.send(json.dumps({"id": n, "method": m, "params": params}))
        while True:
            msg = json.loads(ws.recv())
            if msg.get("id") == n: return msg.get("result", msg)
    cmd("Page.enable"); cmd("Runtime.enable")
    cmd("Emulation.setDeviceMetricsOverride", width=375, height=667, deviceScaleFactor=1, mobile=True)
    cmd("Page.navigate", url=URL)
    time.sleep(6)
    def js(e):
        return cmd("Runtime.evaluate", expression=e, returnByValue=True).get("result", {}).get("value")
    print("scrollHeight:", js("document.documentElement.scrollHeight"), "body:", js("document.body.scrollHeight"))
    print("innerHeight:", js("window.innerHeight"))
    print("before scrollY:", js("window.scrollY"))
    js("window.scrollTo(0, document.documentElement.scrollHeight)")
    time.sleep(1)
    print("after scrollY:", js("window.scrollY"))
    print("maxscroll:", js("document.documentElement.scrollHeight - window.innerHeight"))
    print("html overflow:", js("getComputedStyle(document.documentElement).overflowY"), "body:", js("getComputedStyle(document.body).overflowY"))
    print("main padding-bottom:", js("(()=>{const e=document.querySelector('main'); return e? getComputedStyle(e).paddingBottom:null})()"))
    print("body padding-bottom:", js("getComputedStyle(document.body).paddingBottom"))
    print("scroll-behavior:", js("getComputedStyle(document.documentElement).scrollBehavior"))
    def js2(e):
        return cmd("Runtime.evaluate", expression=e, returnByValue=True).get("result", {}).get("value")
    print("footer bottom @max:", js2("(()=>{const f=document.querySelector('footer'); return f? Math.round(f.getBoundingClientRect().bottom):null})()"))
    print("last text bottom:", js2("""(()=>{const k=[...document.querySelectorAll('footer *')].filter(e=>e.children.length===0&&e.textContent.trim()); const l=k[k.length-1]; return l? Math.round(l.getBoundingClientRect().bottom)+' :: '+l.textContent.trim().slice(0,30):null})()"""))
    print("bar top:", js2("""(()=>{const b=[...document.querySelectorAll('div.fixed.inset-x-0')].find(d=>d.querySelector('button[wire\\\\:click="tambah"]')); return b? Math.round(b.getBoundingClientRect().top):null})()"""))
    ws.close()
finally:
    p.terminate()
    try: p.wait(timeout=10)
    except Exception: p.kill()
    shutil.rmtree(profil, ignore_errors=True)
