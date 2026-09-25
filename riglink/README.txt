HAM SHACK RIG LINK - live frequency from Ham Radio Deluxe on your website
=========================================================================

  radio --CAT--> HRD Rig Control --(port 7809, this PC only)--> Rig Link
  --> your Ham Shack website --> live frequency & mode, automatic ON/OFF AIR.
  Read-only: Rig Link never changes anything on the radio.

SET UP (once)
  Website:
    1. WordPress admin -> Ham Shack -> Live rig (HRD): tick "Enable" and
       "Automatic ON AIR" -> Save.
    2. Copy the TOKEN that appears (shown once; "Generate new token" if lost).
  Shack PC:
    1. Unzip to a permanent folder, e.g. C:\HamShack-RigLink
       (not Downloads - the autostart task uses this path).
    2. Open config.txt in Notepad: set SiteUrl= (your website, no slash at the end)
       and Token= (the token from the website). Save.
    3. Start HRD Rig Control connected to your radio, as usual.
    4. Double-click 1-TEST.bat -> it should show your radio, frequency and mode
       and "Website accepted the data ... everything works!"
    5. Double-click 2-ENABLE-AUTOSTART.bat -> Rig Link now runs hidden in the
       background and starts by itself every time you log in to Windows.

DAY TO DAY
  - Radio + HRD on -> after a few seconds the website shows ON AIR + frequency.
  - Turn the VFO -> the website updates by itself (about every 10 s, no reload).
  - Close HRD / switch the radio off -> OFF AIR after about 30 s.
  - PC switched off at the wall -> OFF AIR after 5 minutes (change in Live rig settings).
  - FT8 / FT4 / JS8 recognised automatically from the dial frequency.
  - A "Portable" status set by hand is left alone - only the frequency is updated.

FILES
  1-TEST.bat               one-off test (shows what it reads and whether the site accepted it)
  2-ENABLE-AUTOSTART.bat   run in the background + start at every Windows log-in
  3-DISABLE-AUTOSTART.bat  stop and remove the autostart
  4-RUN-IN-WINDOW.bat      run in a visible window (for watching / troubleshooting)
  RigLink.log              log file (created automatically)

TROUBLESHOOTING (1-TEST.bat shows the reason)
  "HRD does not answer on 127.0.0.1:7809"
      HRD Rig Control is not running, or its IP server is off / on another port
      (the same interface WSJT-X uses with the "Ham Radio Deluxe" rig setting).
      If you changed the port in HRD, set HrdPort= in config.txt.
  "HRD is running but sees no radio"
      connect HRD Rig Control to the radio (Connect button).
  "Website rejected the TOKEN"
      generate a new token on the website and paste it into config.txt.
  HTTP 403
      "Enable" is not ticked on the website, or the hosting firewall blocks the request.
  After changing config.txt run 3-DISABLE-AUTOSTART.bat, then 2-ENABLE-AUTOSTART.bat.

---------------------------------------------------------------------------
PL (skrót): na stronie Ham Shack -> Live rig (HRD) zaznacz Enable, skopiuj token.
Rozpakuj ten folder np. do C:\HamShack-RigLink, w config.txt wpisz SiteUrl i Token,
włącz HRD, uruchom 1-TEST.bat, a gdy pokaże "everything works!" - 2-ENABLE-AUTOSTART.bat.
Program tylko odczytuje radio, niczego w nim nie zmienia. 73!
