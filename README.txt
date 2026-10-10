HAM SHACK RIG LINK 2.0 - your radio and WSJT-X live on your website
====================================================================

What it does
  1) Frequency and mode from Ham Radio Deluxe -> ON AIR + frequency live on the website.
  2) Live FT8/FT4 decodes from WSJT-X -> table and map of who you hear on the Live page.
  3) QSOs logged in WSJT-X go straight into the website logbook
     (QRZ.com sync later recognises them - no duplicates).
  4) When HRD is not running but WSJT-X is, the frequency comes from WSJT-X.
  Read-only: Rig Link never changes anything in the radio or in WSJT-X.

  radio --CAT--> HRD Rig Control --(TCP 7809)--------> Rig Link --> your website
  WSJT-X -------------------------(UDP 2237, local)--> Rig Link --> your website

UPDATING FROM 1.0
  1. Run 3-DISABLE-AUTOSTART.bat in your old folder.
  2. Replace HamShack-RigLink.ps1 with the new one.
  3. Copy the WSJT-X section from the new config.txt to the end of yours
     (or take the new config.txt and paste your SiteUrl and Token into it).
  4. Set up WSJT-X (below), run 1-TEST.bat, then 2-ENABLE-AUTOSTART.bat.
  Update the Ham Shack theme to 2.0 first - otherwise the website does not know
  the WSJT-X address (Rig Link then says "update the Ham Shack theme").

WSJT-X SETTINGS (once)
  WSJT-X -> File -> Settings -> Reporting -> "UDP Server":
     UDP Server:              127.0.0.1
     UDP Server port number:  2237
  Rig Link listens on this PC only - nothing is opened to the network.

  Also using JTAlert / GridTracker / Log4OM on port 2237?
     Only one program can own a normal UDP port. Use multicast instead:
     set WSJT-X UDP Server to 239.255.0.1 (port 2237), set the same address in
     JTAlert/GridTracker, and in Rig Link's config.txt:  WsjtxMulticast=239.255.0.1

NEW SET-UP
  Website:
    1. WordPress admin -> Ham Shack -> Live rig: tick "Enable" and "Automatic ON AIR"
       -> Save. Leave the two WSJT-X options ticked.
    2. Copy the TOKEN (shown once; "Generate new token" if lost).
  Shack PC:
    1. Unzip to a permanent folder, e.g. C:\HamShack-RigLink (not Downloads).
    2. Open config.txt in Notepad: SiteUrl= (no slash at the end) and Token=. Save.
    3. Start HRD Rig Control and/or WSJT-X.
    4. Double-click 1-TEST.bat -> checks HRD and WSJT-X separately (waits up to 20 s
       for WSJT-X) and ends with "-> everything works!".
    5. Double-click 2-ENABLE-AUTOSTART.bat -> runs hidden, starts at every Windows log-in.

CONFIG.TXT
  HrdEnabled=1         0 = do not use HRD (e.g. WSJT-X controls the radio)
  WsjtxEnabled=1       0 = ignore WSJT-X
  WsjtxPort=2237       the port from WSJT-X Settings -> Reporting
  WsjtxMulticast=      e.g. 239.255.0.1 when sharing with JTAlert/GridTracker
  WsjtxSendSeconds=15  how often to send a batch of decodes
  WsjtxForRig=1        take the frequency from WSJT-X when HRD is not running
  After any change: 3-DISABLE-AUTOSTART.bat, then 2-ENABLE-AUTOSTART.bat.

DAY TO DAY
  - Radio + HRD on -> ON AIR + frequency after a few seconds.
  - WSJT-X on -> decodes appear on the Live page and the home page; stations calling
    you are highlighted in green.
  - Log a QSO in WSJT-X -> in the website logbook, map and stats within seconds.
  - WSJT-X closed -> the FT8 section goes quiet after about 1.5 minutes.
  - HRD closed / radio off -> OFF AIR after about 30 s.

TROUBLESHOOTING (1-TEST.bat shows the reason)
  "HRD does not answer on 127.0.0.1:7809" -> HRD Rig Control not running / other port.
  "no data on UDP port 2237"               -> WSJT-X not running, wrong address/port in
                                              Settings -> Reporting, or another program
                                              owns the port (use multicast, see above).
  "cannot open UDP port 2237"              -> another program already listens -> multicast.
  "Website rejected the TOKEN"             -> generate a new token, paste into config.txt.
  HTTP 403                                 -> "Enable" is off, or the hosting firewall.
  HTTP 404                                 -> update the Ham Shack theme to 2.0.

Files
  1-TEST.bat                one-off test
  2-ENABLE-AUTOSTART.bat    run hidden + start with Windows
  3-DISABLE-AUTOSTART.bat   stop and remove autostart
  4-RUN-IN-WINDOW.bat       run in a visible window (to watch / diagnose)
  RigLink.log               log file (created automatically)

Requirements: Windows 10/11 (PowerShell is built in), HRD Rig Control and/or WSJT-X 2.x.
73!
