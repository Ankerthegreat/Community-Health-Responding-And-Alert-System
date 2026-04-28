// ─────────────────────────────────────────────────────
//  CHRAS — Three.js Background Animation
//  Medical data network — floating nodes + pulse waves
// ─────────────────────────────────────────────────────

(function initCHRASBackground() {
  const canvas = document.getElementById('bgCanvas');
  if (!canvas) return;

  const THREE = window.THREE;
  if (!THREE) return;

  // ── Scene setup ─────────────────────────────────────
  const scene    = new THREE.Scene();
  const W        = window.innerWidth;
  const H        = window.innerHeight;
  const camera   = new THREE.PerspectiveCamera(60, W / H, 0.1, 500);
  camera.position.set(0, 0, 80);

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setSize(W, H);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setClearColor(0x000000, 0);

  // ── Colors ──────────────────────────────────────────
  const GREEN  = new THREE.Color(0x00e87a);
  const GREEN2 = new THREE.Color(0x004422);
  const RED    = new THREE.Color(0xff3b3b);

  // ── Node geometry (health data points) ──────────────
  const NODE_COUNT = 120;
  const nodeGeo    = new THREE.BufferGeometry();
  const positions  = new Float32Array(NODE_COUNT * 3);
  const colors     = new Float32Array(NODE_COUNT * 3);
  const sizes      = new Float32Array(NODE_COUNT);

  for (let i = 0; i < NODE_COUNT; i++) {
    const i3 = i * 3;
    positions[i3]     = (Math.random() - 0.5) * 200;
    positions[i3 + 1] = (Math.random() - 0.5) * 120;
    positions[i3 + 2] = (Math.random() - 0.5) * 60;

    // Most nodes green, a few red (alert nodes)
    const isAlert = Math.random() < 0.07;
    const col     = isAlert ? RED : (Math.random() < 0.5 ? GREEN : GREEN2);
    colors[i3]     = col.r;
    colors[i3 + 1] = col.g;
    colors[i3 + 2] = col.b;

    sizes[i] = isAlert ? 3.5 : Math.random() * 2 + 0.5;
  }

  nodeGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  nodeGeo.setAttribute('color',    new THREE.BufferAttribute(colors, 3));
  nodeGeo.setAttribute('size',     new THREE.BufferAttribute(sizes, 1));

  const nodeMat = new THREE.PointsMaterial({
    size:           2,
    vertexColors:   true,
    transparent:    true,
    opacity:        0.7,
    sizeAttenuation: true,
  });
  const nodes = new THREE.Points(nodeGeo, nodeMat);
  scene.add(nodes);

  // ── Connection lines ─────────────────────────────────
  // Draw edges between nearby nodes
  const linePositions = [];
  const lineColors    = [];

  for (let i = 0; i < NODE_COUNT; i++) {
    const i3 = i * 3;
    const ax  = positions[i3], ay = positions[i3+1], az = positions[i3+2];

    for (let j = i + 1; j < NODE_COUNT; j++) {
      const j3  = j * 3;
      const dx  = ax - positions[j3];
      const dy  = ay - positions[j3+1];
      const dz  = az - positions[j3+2];
      const dist = Math.sqrt(dx*dx + dy*dy + dz*dz);

      if (dist < 30 && Math.random() < 0.35) {
        linePositions.push(ax, ay, az, positions[j3], positions[j3+1], positions[j3+2]);
        const alpha = 1 - dist / 30;
        lineColors.push(0, alpha * 0.25, alpha * 0.12, 0, alpha * 0.25, alpha * 0.12);
      }
    }
  }

  const lineGeo = new THREE.BufferGeometry();
  lineGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(linePositions), 3));
  lineGeo.setAttribute('color',    new THREE.BufferAttribute(new Float32Array(lineColors), 3));
  const lineMat = new THREE.LineBasicMaterial({ vertexColors: true, transparent: true, opacity: 0.5 });
  const linesMesh = new THREE.LineSegments(lineGeo, lineMat);
  scene.add(linesMesh);

  // ── Pulse rings (ripple outward from origin) ─────────
  const pulseRings = [];
  function createRing() {
    const geo = new THREE.RingGeometry(0.1, 0.4, 32);
    const mat = new THREE.MeshBasicMaterial({
      color: GREEN,
      transparent: true,
      opacity: 0.6,
      side: THREE.DoubleSide,
    });
    const mesh = new THREE.Mesh(geo, mat);
    // spawn at random cluster point
    const idx = Math.floor(Math.random() * NODE_COUNT);
    const i3  = idx * 3;
    mesh.position.set(positions[i3], positions[i3+1], positions[i3+2]);
    mesh.rotation.x = Math.PI / 2;
    scene.add(mesh);
    pulseRings.push({ mesh, life: 0 });
  }

  // ── Floating ECG line ────────────────────────────────
  const ECG_POINTS = 120;
  function buildECG(t = 0) {
    const pts = [];
    for (let i = 0; i < ECG_POINTS; i++) {
      const x   = -70 + (i / ECG_POINTS) * 140;
      const phase = (i / ECG_POINTS) * Math.PI * 8 + t;
      // ECG-like waveform
      let y = Math.sin(phase) * 0.4;
      const frac = (i % (ECG_POINTS / 6)) / (ECG_POINTS / 6);
      if (frac > 0.45 && frac < 0.50) y += 6;
      else if (frac > 0.50 && frac < 0.52) y -= 3;
      else if (frac > 0.52 && frac < 0.56) y += 1;
      pts.push(new THREE.Vector3(x, y - 35, 0));
    }
    return pts;
  }

  const ecgCurve = new THREE.CatmullRomCurve3(buildECG());
  const ecgGeo   = new THREE.BufferGeometry().setFromPoints(ecgCurve.getPoints(200));
  const ecgMat   = new THREE.LineBasicMaterial({ color: 0x00e87a, transparent: true, opacity: 0.15 });
  const ecgLine  = new THREE.Line(ecgGeo, ecgMat);
  scene.add(ecgLine);

  // ── Grid plane (subtle) ──────────────────────────────
  const gridHelper = new THREE.GridHelper(200, 30, 0x001a0d, 0x001a0d);
  gridHelper.position.y = -40;
  gridHelper.material.transparent = true;
  gridHelper.material.opacity = 0.25;
  scene.add(gridHelper);

  // ── Animation loop ────────────────────────────────────
  let t = 0;
  let lastRing = 0;

  function animate() {
    requestAnimationFrame(animate);
    t += 0.008;

    // Slowly rotate the whole node cloud
    nodes.rotation.y    = t * 0.05;
    linesMesh.rotation.y = t * 0.05;

    // Pulse the node opacity
    nodeMat.opacity = 0.55 + Math.sin(t * 0.7) * 0.15;

    // Update ECG
    const newPts = buildECG(t * 2).map(p => [p.x, p.y, p.z]);
    const posArr = ecgGeo.attributes.position.array;
    const curvePts = new THREE.CatmullRomCurve3(buildECG(t * 2)).getPoints(200);
    for (let i = 0; i < curvePts.length; i++) {
      posArr[i*3]   = curvePts[i].x;
      posArr[i*3+1] = curvePts[i].y;
      posArr[i*3+2] = curvePts[i].z;
    }
    ecgGeo.attributes.position.needsUpdate = true;

    // Create new pulse rings periodically
    if (t - lastRing > 1.2) { createRing(); lastRing = t; }

    // Animate pulse rings
    for (let i = pulseRings.length - 1; i >= 0; i--) {
      const ring = pulseRings[i];
      ring.life += 0.025;
      ring.mesh.scale.setScalar(1 + ring.life * 8);
      ring.mesh.material.opacity = Math.max(0, 0.5 - ring.life * 0.5);
      if (ring.life > 1) {
        scene.remove(ring.mesh);
        ring.mesh.geometry.dispose();
        pulseRings.splice(i, 1);
      }
    }

    // Gentle camera drift
    camera.position.x = Math.sin(t * 0.12) * 8;
    camera.position.y = Math.cos(t * 0.08) * 4;
    camera.lookAt(0, 0, 0);

    renderer.render(scene, camera);
  }
  animate();

  // ── Resize handler ────────────────────────────────────
  window.addEventListener('resize', () => {
    const w = window.innerWidth, h = window.innerHeight;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
  });
})();
