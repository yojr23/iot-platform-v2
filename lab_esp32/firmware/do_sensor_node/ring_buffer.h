// ring_buffer.h - FIFO circular de tamano fijo, sin dependencias de Arduino.
// Si se llena, push() descarta el mas viejo y cuenta en dropped().
#pragma once
#include <stddef.h>
#include <stdint.h>

template <typename T, size_t N>
class RingBuffer {
 public:
  bool empty() const { return count_ == 0; }
  bool full() const { return count_ == N; }
  size_t size() const { return count_; }
  uint32_t dropped() const { return dropped_; }

  void push(const T &v) {
    if (full()) {  // descarta el mas viejo
      head_ = (head_ + 1) % N;
      count_--;
      dropped_++;
    }
    buf_[(head_ + count_) % N] = v;
    count_++;
  }
  // Mas viejo, sin sacarlo (para sacarlo solo si la publicacion tuvo exito). Requiere !empty().
  const T &front() const { return buf_[head_]; }
  void pop() {
    if (empty()) return;
    head_ = (head_ + 1) % N;
    count_--;
  }

 private:
  T buf_[N];
  size_t head_ = 0, count_ = 0;
  uint32_t dropped_ = 0;
};
