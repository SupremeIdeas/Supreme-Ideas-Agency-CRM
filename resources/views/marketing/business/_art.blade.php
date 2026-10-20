{{-- Illustrations for the NaaraBusiness Suite problem/solution cards. They reuse the .ill class vocabulary of the homepage bento (sf, sf2, fT, fTs, fG, ln, ico ...), so they follow light and dark the same way. --}}
@switch($id)
@case('p1')
<svg class="ill" viewBox="0 0 320 200" role="img" aria-label="Messages from five different apps reaching five different people">
<circle class="fT" cx="40" cy="30" r="18"/><circle class="fC" cx="40" cy="100" r="18"/><circle class="fG" cx="40" cy="170" r="18"/>
<path class="ln" d="M58 30H140M58 100H140M58 170H140"/>
<rect class="sf2" x="140" y="12" width="88" height="36" rx="12"/><text class="b" x="152" y="35">Ana</text>
<rect class="sf2" x="140" y="82" width="88" height="36" rx="12"/><text class="b" x="152" y="105">Tunde</text>
<rect class="sf2" x="140" y="152" width="88" height="36" rx="12"/><text class="b" x="152" y="175">Zainab</text>
<rect class="fCs" x="238" y="84" width="74" height="32" rx="16"/><text class="ct b" x="275" y="104" text-anchor="middle" style="font-size:10.5px">Who has it?</text>
</svg>
@break
@case('p2')
<svg class="ill" viewBox="0 0 320 200" role="img" aria-label="A phone ringing with nobody to answer it">
<rect class="sf" x="104" y="12" width="112" height="176" rx="20"/>
<rect class="fCs" x="116" y="34" width="88" height="40" rx="12"/><text class="ct b" x="160" y="58" text-anchor="middle" style="font-size:12px">Missed call</text>
<rect class="sf2" x="116" y="84" width="88" height="30" rx="10"/><text class="m" x="160" y="104" text-anchor="middle" style="font-size:10.5px">Missed call</text>
<rect class="sf2" x="116" y="122" width="88" height="30" rx="10"/><text class="m" x="160" y="142" text-anchor="middle" style="font-size:10.5px">Voicemail full</text>
<path class="ln dash" d="M60 60c-14 10-14 30 0 40M44 48c-26 18-26 54 0 72"/>
<path class="ln dash" d="M260 60c14 10 14 30 0 40M276 48c26 18 26 54 0 72"/>
</svg>
@break
@case('p3')
<svg class="ill" viewBox="0 0 320 200" role="img" aria-label="A chat history with the middle of the conversation missing">
<rect class="sf" x="20" y="14" width="280" height="172" rx="16"/>
<rect class="sf2" x="36" y="32" width="140" height="28" rx="14"/>
<rect class="fTs" x="124" y="72" width="160" height="28" rx="14"/>
<rect class="sf2 dash" x="36" y="112" width="200" height="28" rx="14"/><text class="ct b" x="136" y="130" text-anchor="middle" style="font-size:11px">What was promised?</text>
<text class="m" x="36" y="168" style="font-size:10.5px">No shared record</text>
</svg>
@break
@case('p4')
<svg class="ill" viewBox="0 0 320 200" role="img" aria-label="A shop sign nobody can find among many others">
<rect class="sf2" x="16" y="30" width="80" height="48" rx="10"/><rect class="sf2" x="120" y="30" width="80" height="48" rx="10"/><rect class="sf2" x="224" y="30" width="80" height="48" rx="10"/>
<rect class="sf2" x="16" y="96" width="80" height="48" rx="10"/><rect class="sf2 dash" x="120" y="96" width="80" height="48" rx="10"/><rect class="sf2" x="224" y="96" width="80" height="48" rx="10"/>
<text class="ct b" x="160" y="125" text-anchor="middle" style="font-size:12px">You?</text>
<text class="m" x="160" y="176" text-anchor="middle" style="font-size:10.5px">Unverified, unseen</text>
</svg>
@break
@case('s1')
<svg class="ill" viewBox="0 0 320 200" role="img" aria-label="WhatsApp, Telegram, Instagram and SMS flowing into one shared inbox">
<rect class="fT" x="14" y="18" width="84" height="28" rx="14"/><text class="w b" x="56" y="37" text-anchor="middle" style="font-size:11px">WhatsApp</text>
<rect class="fC" x="14" y="62" width="84" height="28" rx="14"/><text class="w b" x="56" y="81" text-anchor="middle" style="font-size:11px">Telegram</text>
<rect class="fG" x="14" y="106" width="84" height="28" rx="14"/><text class="w b" x="56" y="125" text-anchor="middle" style="font-size:11px">Instagram</text>
<rect class="sf2" x="14" y="150" width="84" height="28" rx="14"/><text class="b" x="56" y="169" text-anchor="middle" style="font-size:11px">SMS</text>
<path class="ln" d="M98 32H150M98 76H150M98 120H150M98 164H150"/>
<rect class="sf" x="150" y="14" width="156" height="172" rx="16"/>
<text class="b" x="166" y="40">Shared inbox</text>
<rect class="fTs" x="164" y="52" width="128" height="30" rx="10"/><rect class="sf2" x="164" y="90" width="128" height="30" rx="10"/><rect class="sf2" x="164" y="128" width="128" height="30" rx="10"/>
<circle class="fG" cx="278" cy="67" r="7"/>
</svg>
@break
@case('s2')
<svg class="ill" viewBox="0 0 320 200" role="img" aria-label="An incoming call routed by a menu to a team, a voicemail or the assistant">
<circle class="fT" cx="36" cy="100" r="20"/><path class="ico sW" fill="none" d="M28 100h16M38 92l8 8-8 8"/>
<path class="ln" d="M56 100H98"/>
<rect class="sf" x="98" y="74" width="80" height="52" rx="12"/><text class="b" x="138" y="96" text-anchor="middle" style="font-size:11px">Menu</text><text class="m" x="138" y="113" text-anchor="middle" style="font-size:10px">Press 1, 2, 3</text>
<path class="ln" d="M178 88L228 40M178 100H228M178 112L228 160"/>
<rect class="fTs" x="228" y="24" width="80" height="32" rx="16"/><text class="tt b" x="268" y="44" text-anchor="middle" style="font-size:10.5px">Sales team</text>
<rect class="sf2" x="228" y="84" width="80" height="32" rx="16"/><text class="b" x="268" y="104" text-anchor="middle" style="font-size:10.5px">Assistant</text>
<rect class="sf2" x="228" y="144" width="80" height="32" rx="16"/><text class="b" x="268" y="164" text-anchor="middle" style="font-size:10.5px">Voicemail</text>
</svg>
@break
@case('s3')
<svg class="ill" viewBox="0 0 320 200" role="img" aria-label="Four roles sharing one wallet, each with a spend cap">
<rect class="fT" x="110" y="12" width="100" height="44" rx="14"/><text class="w b" x="160" y="40" text-anchor="middle" style="font-size:12px">One wallet</text>
<path class="ln" d="M130 56L50 110M150 56L125 110M170 56L195 110M190 56L270 110"/>
<rect class="sf2" x="12" y="110" width="76" height="64" rx="12"/><text class="b" x="50" y="136" text-anchor="middle" style="font-size:11px">Owner</text><text class="m" x="50" y="154" text-anchor="middle" style="font-size:10px">everything</text>
<rect class="sf2" x="94" y="110" width="76" height="64" rx="12"/><text class="b" x="132" y="136" text-anchor="middle" style="font-size:11px">Admin</text><text class="m" x="132" y="154" text-anchor="middle" style="font-size:10px">manage</text>
<rect class="sf2" x="176" y="110" width="76" height="64" rx="12"/><text class="b" x="214" y="136" text-anchor="middle" style="font-size:11px">Agent</text><text class="m" x="214" y="154" text-anchor="middle" style="font-size:10px">cap $50</text>
<rect class="sf2" x="258" y="110" width="52" height="64" rx="12"/><text class="b" x="284" y="136" text-anchor="middle" style="font-size:10.5px">Viewer</text><text class="m" x="284" y="154" text-anchor="middle" style="font-size:10px">read</text>
</svg>
@break
@case('s4')
<svg class="ill" viewBox="0 0 320 200" role="img" aria-label="A brand card in the directory carrying a verified seal and a follower target">
<rect class="sf" x="40" y="18" width="240" height="164" rx="18"/>
<circle class="fT" cx="84" cy="64" r="22"/><text class="w h" x="84" y="72" text-anchor="middle" style="font-size:22px">N</text>
<text class="b" x="118" y="60" style="font-size:14px">Your brand</text><text class="m" x="118" y="78" style="font-size:10.5px">Naara Pro</text>
<circle class="fG" cx="252" cy="52" r="14"/><path class="ico sW" fill="none" style="stroke-width:2.4" d="M245 52l5 5 9-10"/>
<rect class="sf2" x="58" y="102" width="204" height="12" rx="6"/><rect class="fT" x="58" y="102" width="140" height="12" rx="6"/>
<text class="m" x="58" y="136" style="font-size:10.5px">Monthly follower target</text>
<rect class="fTs" x="58" y="148" width="110" height="22" rx="11"/><text class="tt b" x="113" y="163" text-anchor="middle" style="font-size:10.5px">Boost if short</text>
</svg>
@break
@endswitch
